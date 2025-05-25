<?php

namespace RiaanZA\LaravelSubscription\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Services\SubscriptionService;
use RiaanZA\LaravelSubscription\Http\Requests\CreateSubscriptionRequest;
use RiaanZA\LaravelSubscription\Http\Requests\UpdateSubscriptionRequest;
use RiaanZA\LaravelSubscription\Http\Requests\CancelSubscriptionRequest;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Display the user's subscription dashboard.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->with(['plan.features', 'usage'])
            ->whereIn('status', ['active', 'trial', 'cancelled', 'past_due'])
            ->latest()
            ->first();

        $subscriptionData = null;
        if ($subscription) {
            $subscriptionData = [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'formatted_amount' => $subscription->formatted_amount,
                'currency' => $subscription->currency,
                'current_period_start' => $subscription->current_period_start->toDateString(),
                'current_period_end' => $subscription->current_period_end->toDateString(),
                'next_billing_date' => $subscription->next_billing_date->toDateString(),
                'days_remaining' => $subscription->days_remaining,
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'trial_days_remaining' => $subscription->trial_days_remaining,
                'cancelled_at' => $subscription->cancelled_at?->toDateString(),
                'ends_at' => $subscription->ends_at?->toDateString(),
                'is_active' => $subscription->isActive(),
                'on_trial' => $subscription->onTrial(),
                'is_cancelled' => $subscription->isCancelled(),
                'is_past_due' => $subscription->isPastDue(),
                'is_expired' => $subscription->isExpired(),
                'is_ending_soon' => $subscription->isEndingSoon(),
                'is_trial_ending_soon' => $subscription->isTrialEndingSoon(),
                'plan' => [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                    'description' => $subscription->plan->description,
                    'price' => $subscription->plan->price,
                    'formatted_price' => $subscription->plan->formatted_price,
                    'billing_cycle' => $subscription->plan->billing_cycle,
                    'billing_cycle_human' => $subscription->plan->billing_cycle_human,
                    'features' => $subscription->plan->features->map(function ($feature) {
                        return [
                            'key' => $feature->feature_key,
                            'name' => $feature->feature_name,
                            'type' => $feature->feature_type,
                            'limit' => $feature->typed_limit,
                            'human_limit' => $feature->human_limit,
                            'is_unlimited' => $feature->is_unlimited,
                            'description' => $feature->description,
                        ];
                    }),
                ],
                'usage' => $subscription->usage->map(function ($usage) {
                    return [
                        'feature_key' => $usage->feature_key,
                        'usage_count' => $usage->usage_count,
                        'period_start' => $usage->period_start->toDateString(),
                        'period_end' => $usage->period_end->toDateString(),
                    ];
                }),
            ];
        }

        // Get available plans for upgrades/downgrades
        $availablePlans = SubscriptionPlan::active()
            ->ordered()
            ->with('features')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'formatted_price' => $plan->formatted_price,
                    'billing_cycle' => $plan->billing_cycle,
                    'billing_cycle_human' => $plan->billing_cycle_human,
                    'trial_days' => $plan->trial_days,
                    'has_trial_period' => $plan->hasTrialPeriod(),
                    'is_popular' => $plan->is_popular,
                ];
            });

        if ($request->expectsJson()) {
            return response()->json([
                'subscription' => $subscriptionData,
                'available_plans' => $availablePlans,
            ]);
        }

        return Inertia::render('Subscription/Dashboard', [
            'subscription' => $subscriptionData,
            'available_plans' => $availablePlans,
        ]);
    }

    /**
     * Create a new subscription.
     */
    public function store(CreateSubscriptionRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $plan = SubscriptionPlan::where('slug', $request->plan_slug)->firstOrFail();

        try {
            $subscription = $this->subscriptionService->createSubscription(
                $user,
                $plan,
                $request->payment_data ?? [],
                $request->boolean('start_trial', false)
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Subscription created successfully',
                    'subscription' => [
                        'id' => $subscription->id,
                        'status' => $subscription->status,
                        'plan_name' => $subscription->plan->name,
                    ],
                ], 201);
            }

            return redirect()
                ->route('subscription.dashboard')
                ->with('success', 'Subscription created successfully!');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to create subscription',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors(['subscription' => $e->getMessage()]);
        }
    }

    /**
     * Update subscription (change plan).
     */
    public function update(UpdateSubscriptionRequest $request, UserSubscription $subscription): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $subscription);

        $newPlan = SubscriptionPlan::where('slug', $request->plan_slug)->firstOrFail();

        try {
            $updatedSubscription = $this->subscriptionService->changePlan(
                $subscription,
                $newPlan,
                $request->boolean('prorate', true)
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Subscription updated successfully',
                    'subscription' => [
                        'id' => $updatedSubscription->id,
                        'status' => $updatedSubscription->status,
                        'plan_name' => $updatedSubscription->plan->name,
                    ],
                ]);
            }

            return redirect()
                ->route('subscription.dashboard')
                ->with('success', 'Subscription updated successfully!');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to update subscription',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors(['subscription' => $e->getMessage()]);
        }
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(CancelSubscriptionRequest $request, UserSubscription $subscription): JsonResponse|RedirectResponse
    {
        $this->authorize('cancel', $subscription);

        try {
            $cancelledSubscription = $this->subscriptionService->cancelSubscription(
                $subscription,
                $request->boolean('immediately', false),
                $request->cancellation_reason
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Subscription cancelled successfully',
                    'subscription' => [
                        'id' => $cancelledSubscription->id,
                        'status' => $cancelledSubscription->status,
                        'cancelled_at' => $cancelledSubscription->cancelled_at->toDateString(),
                        'ends_at' => $cancelledSubscription->ends_at?->toDateString(),
                    ],
                ]);
            }

            return redirect()
                ->route('subscription.dashboard')
                ->with('success', 'Subscription cancelled successfully.');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to cancel subscription',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors(['subscription' => $e->getMessage()]);
        }
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resume(Request $request, UserSubscription $subscription): JsonResponse|RedirectResponse
    {
        $this->authorize('resume', $subscription);

        try {
            $resumedSubscription = $this->subscriptionService->resumeSubscription($subscription);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Subscription resumed successfully',
                    'subscription' => [
                        'id' => $resumedSubscription->id,
                        'status' => $resumedSubscription->status,
                    ],
                ]);
            }

            return redirect()
                ->route('subscription.dashboard')
                ->with('success', 'Subscription resumed successfully!');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to resume subscription',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors(['subscription' => $e->getMessage()]);
        }
    }

    /**
     * Get current subscription usage.
     */
    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()
            ->with(['plan.features', 'usage'])
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        $usageData = [];
        foreach ($subscription->plan->features as $feature) {
            if ($feature->feature_type === 'numeric') {
                $currentUsage = $subscription->getCurrentUsage($feature->feature_key);
                $limit = $feature->typed_limit;
                
                $usageData[] = [
                    'feature_key' => $feature->feature_key,
                    'feature_name' => $feature->feature_name,
                    'current_usage' => $currentUsage,
                    'limit' => $limit,
                    'is_unlimited' => $feature->is_unlimited,
                    'percentage_used' => $feature->is_unlimited ? 0 : ($limit > 0 ? ($currentUsage / $limit) * 100 : 0),
                    'is_over_limit' => !$feature->is_unlimited && $currentUsage > $limit,
                    'is_near_limit' => !$feature->is_unlimited && $limit > 0 && ($currentUsage / $limit) >= 0.8,
                ];
            }
        }

        return response()->json([
            'usage' => $usageData,
            'subscription_id' => $subscription->id,
            'period_start' => $subscription->current_period_start->toDateString(),
            'period_end' => $subscription->current_period_end->toDateString(),
        ]);
    }
}
