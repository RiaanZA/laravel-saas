<?php

namespace RiaanZA\LaravelSubscription\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Services\UsageService;
use RiaanZA\LaravelSubscription\Services\FeatureService;
use RiaanZA\LaravelSubscription\Http\Requests\IncrementUsageRequest;

class UsageController extends Controller
{
    public function __construct(
        protected UsageService $usageService,
        protected FeatureService $featureService
    ) {}

    /**
     * Get current usage for all features.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->with(['plan.features'])
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        $usageSummary = $this->featureService->getSubscriptionFeatureUsageSummary($subscription);

        return response()->json([
            'subscription_id' => $subscription->id,
            'plan_name' => $subscription->plan->name,
            'period_start' => $subscription->current_period_start->toDateString(),
            'period_end' => $subscription->current_period_end->toDateString(),
            'features' => $usageSummary,
        ]);
    }

    /**
     * Get usage for a specific feature.
     */
    public function show(Request $request, string $featureKey): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->with(['plan.features'])
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        if (!$this->featureService->subscriptionHasFeature($subscription, $featureKey)) {
            return response()->json([
                'message' => 'Feature not available in your subscription plan',
            ], 403);
        }

        $feature = $subscription->plan->features()
            ->where('feature_key', $featureKey)
            ->first();

        $currentUsage = $this->usageService->getCurrentUsage($subscription, $featureKey);
        $usageHistory = $this->usageService->getUsageHistory($subscription, $featureKey, 6);

        $data = [
            'feature_key' => $featureKey,
            'feature_name' => $feature->feature_name,
            'feature_type' => $feature->feature_type,
            'current_usage' => $currentUsage,
            'limit' => $feature->typed_limit,
            'human_limit' => $feature->human_limit,
            'is_unlimited' => $feature->is_unlimited,
            'period_start' => $subscription->current_period_start->toDateString(),
            'period_end' => $subscription->current_period_end->toDateString(),
            'usage_history' => $usageHistory,
        ];

        if ($feature->feature_type === 'numeric' && !$feature->is_unlimited) {
            $data['percentage_used'] = $this->usageService->getUsagePercentage($subscription, $featureKey);
            $data['remaining'] = $this->usageService->getRemainingUsage($subscription, $featureKey);
            $data['is_over_limit'] = $this->featureService->isSubscriptionFeatureOverLimit($subscription, $featureKey);
            $data['is_near_limit'] = $this->featureService->isSubscriptionFeatureNearLimit($subscription, $featureKey);
        }

        return response()->json($data);
    }

    /**
     * Increment usage for a feature.
     */
    public function increment(IncrementUsageRequest $request): JsonResponse
    {
        $user = $request->user();
        $featureKey = $request->feature_key;
        $increment = $request->increment ?? 1;

        try {
            // Check if user can use the feature
            if (!$this->featureService->canUseFeature($user, $featureKey, $increment)) {
                return response()->json([
                    'message' => 'Feature usage limit would be exceeded',
                    'error' => 'usage_limit_exceeded',
                    'feature' => $featureKey,
                ], 429); // Too Many Requests
            }

            $this->usageService->incrementUsage($user, $featureKey, $increment);

            $subscription = $user->subscriptions()
                ->whereIn('status', ['active', 'trial'])
                ->latest()
                ->first();

            $newUsage = $this->usageService->getCurrentUsage($subscription, $featureKey);

            return response()->json([
                'message' => 'Usage incremented successfully',
                'feature_key' => $featureKey,
                'increment' => $increment,
                'new_usage' => $newUsage,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to increment usage',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Decrement usage for a feature.
     */
    public function decrement(Request $request): JsonResponse
    {
        $request->validate([
            'feature_key' => 'required|string',
            'decrement' => 'integer|min:1|max:1000',
        ]);

        $user = $request->user();
        $featureKey = $request->feature_key;
        $decrement = $request->decrement ?? 1;

        try {
            $this->usageService->decrementUsage($user, $featureKey, $decrement);

            $subscription = $user->subscriptions()
                ->whereIn('status', ['active', 'trial'])
                ->latest()
                ->first();

            $newUsage = $this->usageService->getCurrentUsage($subscription, $featureKey);

            return response()->json([
                'message' => 'Usage decremented successfully',
                'feature_key' => $featureKey,
                'decrement' => $decrement,
                'new_usage' => $newUsage,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to decrement usage',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reset usage for a feature.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'feature_key' => 'required|string',
        ]);

        $user = $request->user();
        $featureKey = $request->feature_key;

        $subscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        try {
            $this->usageService->resetUsage($subscription, $featureKey);

            return response()->json([
                'message' => 'Usage reset successfully',
                'feature_key' => $featureKey,
                'new_usage' => 0,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset usage',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get usage alerts (over limit and near limit features).
     */
    public function alerts(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->with(['plan.features'])
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        $overLimitFeatures = $this->featureService->getSubscriptionOverLimitFeatures($subscription);
        $nearLimitFeatures = $this->featureService->getSubscriptionNearLimitFeatures($subscription);

        return response()->json([
            'subscription_id' => $subscription->id,
            'over_limit_features' => $overLimitFeatures,
            'near_limit_features' => $nearLimitFeatures,
            'has_alerts' => !empty($overLimitFeatures) || !empty($nearLimitFeatures),
        ]);
    }

    /**
     * Get usage statistics for a subscription.
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->with(['plan.features'])
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        $features = $subscription->plan->features()
            ->where('feature_type', 'numeric')
            ->get();

        $statistics = [];
        $totalUsagePercentage = 0;
        $numericFeaturesCount = 0;

        foreach ($features as $feature) {
            $currentUsage = $this->usageService->getCurrentUsage($subscription, $feature->feature_key);
            $usagePercentage = $feature->is_unlimited ? 0 : 
                $this->usageService->getUsagePercentage($subscription, $feature->feature_key);

            $statistics[] = [
                'feature_key' => $feature->feature_key,
                'feature_name' => $feature->feature_name,
                'current_usage' => $currentUsage,
                'limit' => $feature->typed_limit,
                'is_unlimited' => $feature->is_unlimited,
                'usage_percentage' => $usagePercentage,
                'remaining' => $this->usageService->getRemainingUsage($subscription, $feature->feature_key),
            ];

            if (!$feature->is_unlimited) {
                $totalUsagePercentage += $usagePercentage;
                $numericFeaturesCount++;
            }
        }

        $averageUsagePercentage = $numericFeaturesCount > 0 ? 
            $totalUsagePercentage / $numericFeaturesCount : 0;

        return response()->json([
            'subscription_id' => $subscription->id,
            'plan_name' => $subscription->plan->name,
            'period_start' => $subscription->current_period_start->toDateString(),
            'period_end' => $subscription->current_period_end->toDateString(),
            'average_usage_percentage' => round($averageUsagePercentage, 2),
            'features' => $statistics,
        ]);
    }
}
