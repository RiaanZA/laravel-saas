<?php

namespace RiaanZA\LaravelSubscription\Services;

use Illuminate\Database\Eloquent\Model;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Models\PlanFeature;
use RiaanZA\LaravelSubscription\Models\SubscriptionUsage;
use Illuminate\Support\Facades\Cache;
use Exception;

class FeatureService
{
    protected UsageService $usageService;

    public function __construct(UsageService $usageService)
    {
        $this->usageService = $usageService;
    }

    /**
     * Check if a user has access to a specific feature.
     */
    public function hasFeature(Model $user, string $featureKey): bool
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return false;
        }

        return $this->subscriptionHasFeature($subscription, $featureKey);
    }

    /**
     * Check if a subscription has a specific feature.
     */
    public function subscriptionHasFeature(UserSubscription $subscription, string $featureKey): bool
    {
        $cacheKey = "subscription_feature_{$subscription->id}_{$featureKey}";
        
        if (config('laravel-subscription.cache.enabled', true)) {
            return Cache::remember($cacheKey, config('laravel-subscription.cache.ttl', 3600), function () use ($subscription, $featureKey) {
                return $this->checkFeatureAccess($subscription, $featureKey);
            });
        }

        return $this->checkFeatureAccess($subscription, $featureKey);
    }

    /**
     * Get feature limit for a user.
     */
    public function getFeatureLimit(Model $user, string $featureKey): mixed
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return null;
        }

        return $this->getSubscriptionFeatureLimit($subscription, $featureKey);
    }

    /**
     * Get feature limit for a subscription.
     */
    public function getSubscriptionFeatureLimit(UserSubscription $subscription, string $featureKey): mixed
    {
        $feature = $this->getFeature($subscription, $featureKey);
        
        if (!$feature) {
            return null;
        }

        return $feature->typed_limit;
    }

    /**
     * Check if user can use a feature (considering usage limits).
     */
    public function canUseFeature(Model $user, string $featureKey, int $increment = 1): bool
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return false;
        }

        return $this->canSubscriptionUseFeature($subscription, $featureKey, $increment);
    }

    /**
     * Check if subscription can use a feature (considering usage limits).
     */
    public function canSubscriptionUseFeature(UserSubscription $subscription, string $featureKey, int $increment = 1): bool
    {
        // First check if feature exists
        if (!$this->subscriptionHasFeature($subscription, $featureKey)) {
            return false;
        }

        $feature = $this->getFeature($subscription, $featureKey);
        
        if (!$feature) {
            return false;
        }

        // Unlimited features are always available
        if ($feature->is_unlimited) {
            return true;
        }

        // For boolean features, just check if enabled
        if ($feature->feature_type === 'boolean') {
            return (bool) $feature->feature_limit;
        }

        // For numeric features, check usage limits
        if ($feature->feature_type === 'numeric') {
            $currentUsage = $this->usageService->getCurrentUsage($subscription, $featureKey);
            $limit = $feature->typed_limit;
            
            return ($currentUsage + $increment) <= $limit;
        }

        // For text features, just check if feature exists
        return true;
    }

    /**
     * Get all features for a user's subscription.
     */
    public function getUserFeatures(Model $user): array
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return [];
        }

        return $this->getSubscriptionFeatures($subscription);
    }

    /**
     * Get all features for a subscription.
     */
    public function getSubscriptionFeatures(UserSubscription $subscription): array
    {
        $cacheKey = "subscription_features_{$subscription->id}";
        
        if (config('laravel-subscription.cache.enabled', true)) {
            return Cache::remember($cacheKey, config('laravel-subscription.cache.ttl', 3600), function () use ($subscription) {
                return $this->buildFeaturesList($subscription);
            });
        }

        return $this->buildFeaturesList($subscription);
    }

    /**
     * Get feature usage summary for a user.
     */
    public function getFeatureUsageSummary(Model $user): array
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return [];
        }

        return $this->getSubscriptionFeatureUsageSummary($subscription);
    }

    /**
     * Get feature usage summary for a subscription.
     */
    public function getSubscriptionFeatureUsageSummary(UserSubscription $subscription): array
    {
        $features = $subscription->plan->features;
        $summary = [];

        foreach ($features as $feature) {
            $featureData = [
                'key' => $feature->feature_key,
                'name' => $feature->feature_name,
                'type' => $feature->feature_type,
                'limit' => $feature->typed_limit,
                'human_limit' => $feature->human_limit,
                'is_unlimited' => $feature->is_unlimited,
                'description' => $feature->description,
            ];

            if ($feature->feature_type === 'numeric') {
                $currentUsage = $this->usageService->getCurrentUsage($subscription, $feature->feature_key);
                $featureData['current_usage'] = $currentUsage;
                $featureData['percentage_used'] = $feature->is_unlimited ? 0 : 
                    ($feature->typed_limit > 0 ? ($currentUsage / $feature->typed_limit) * 100 : 0);
                $featureData['is_over_limit'] = !$feature->is_unlimited && $currentUsage > $feature->typed_limit;
                $featureData['is_near_limit'] = !$feature->is_unlimited && $feature->typed_limit > 0 && 
                    ($currentUsage / $feature->typed_limit) >= 0.8;
                $featureData['remaining'] = $feature->is_unlimited ? 'unlimited' : 
                    max(0, $feature->typed_limit - $currentUsage);
            } elseif ($feature->feature_type === 'boolean') {
                $featureData['is_enabled'] = (bool) $feature->feature_limit;
            }

            $summary[] = $featureData;
        }

        return $summary;
    }

    /**
     * Check if a feature is over its limit.
     */
    public function isFeatureOverLimit(Model $user, string $featureKey): bool
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return false;
        }

        return $this->isSubscriptionFeatureOverLimit($subscription, $featureKey);
    }

    /**
     * Check if a subscription feature is over its limit.
     */
    public function isSubscriptionFeatureOverLimit(UserSubscription $subscription, string $featureKey): bool
    {
        $feature = $this->getFeature($subscription, $featureKey);
        
        if (!$feature || $feature->is_unlimited || $feature->feature_type !== 'numeric') {
            return false;
        }

        $currentUsage = $this->usageService->getCurrentUsage($subscription, $featureKey);
        return $currentUsage > $feature->typed_limit;
    }

    /**
     * Check if a feature is near its limit.
     */
    public function isFeatureNearLimit(Model $user, string $featureKey, float $threshold = 0.8): bool
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return false;
        }

        return $this->isSubscriptionFeatureNearLimit($subscription, $featureKey, $threshold);
    }

    /**
     * Check if a subscription feature is near its limit.
     */
    public function isSubscriptionFeatureNearLimit(UserSubscription $subscription, string $featureKey, float $threshold = 0.8): bool
    {
        $feature = $this->getFeature($subscription, $featureKey);
        
        if (!$feature || $feature->is_unlimited || $feature->feature_type !== 'numeric' || $feature->typed_limit <= 0) {
            return false;
        }

        $currentUsage = $this->usageService->getCurrentUsage($subscription, $featureKey);
        $usagePercentage = $currentUsage / $feature->typed_limit;
        
        return $usagePercentage >= $threshold;
    }

    /**
     * Get features that are over their limits.
     */
    public function getOverLimitFeatures(Model $user): array
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return [];
        }

        return $this->getSubscriptionOverLimitFeatures($subscription);
    }

    /**
     * Get subscription features that are over their limits.
     */
    public function getSubscriptionOverLimitFeatures(UserSubscription $subscription): array
    {
        $features = $subscription->plan->features()
            ->where('feature_type', 'numeric')
            ->where('is_unlimited', false)
            ->get();

        $overLimitFeatures = [];

        foreach ($features as $feature) {
            if ($this->isSubscriptionFeatureOverLimit($subscription, $feature->feature_key)) {
                $currentUsage = $this->usageService->getCurrentUsage($subscription, $feature->feature_key);
                $overLimitFeatures[] = [
                    'key' => $feature->feature_key,
                    'name' => $feature->feature_name,
                    'limit' => $feature->typed_limit,
                    'current_usage' => $currentUsage,
                    'overage' => $currentUsage - $feature->typed_limit,
                ];
            }
        }

        return $overLimitFeatures;
    }

    /**
     * Get features that are near their limits.
     */
    public function getNearLimitFeatures(Model $user, float $threshold = 0.8): array
    {
        $subscription = $this->getActiveSubscription($user);
        
        if (!$subscription) {
            return [];
        }

        return $this->getSubscriptionNearLimitFeatures($subscription, $threshold);
    }

    /**
     * Get subscription features that are near their limits.
     */
    public function getSubscriptionNearLimitFeatures(UserSubscription $subscription, float $threshold = 0.8): array
    {
        $features = $subscription->plan->features()
            ->where('feature_type', 'numeric')
            ->where('is_unlimited', false)
            ->get();

        $nearLimitFeatures = [];

        foreach ($features as $feature) {
            if ($this->isSubscriptionFeatureNearLimit($subscription, $feature->feature_key, $threshold)) {
                $currentUsage = $this->usageService->getCurrentUsage($subscription, $feature->feature_key);
                $usagePercentage = $feature->typed_limit > 0 ? ($currentUsage / $feature->typed_limit) * 100 : 0;
                
                $nearLimitFeatures[] = [
                    'key' => $feature->feature_key,
                    'name' => $feature->feature_name,
                    'limit' => $feature->typed_limit,
                    'current_usage' => $currentUsage,
                    'percentage_used' => $usagePercentage,
                    'remaining' => max(0, $feature->typed_limit - $currentUsage),
                ];
            }
        }

        return $nearLimitFeatures;
    }

    /**
     * Clear feature cache for a subscription.
     */
    public function clearFeatureCache(UserSubscription $subscription): void
    {
        if (!config('laravel-subscription.cache.enabled', true)) {
            return;
        }

        $cachePrefix = config('laravel-subscription.cache.prefix', 'laravel_subscription');
        
        // Clear subscription features cache
        Cache::forget("subscription_features_{$subscription->id}");
        
        // Clear individual feature caches
        foreach ($subscription->plan->features as $feature) {
            Cache::forget("subscription_feature_{$subscription->id}_{$feature->feature_key}");
        }
    }

    /**
     * Get user's active subscription.
     */
    protected function getActiveSubscription(Model $user): ?UserSubscription
    {
        return $user->subscriptions()
            ->with(['plan.features'])
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();
    }

    /**
     * Check feature access for a subscription.
     */
    protected function checkFeatureAccess(UserSubscription $subscription, string $featureKey): bool
    {
        return $subscription->plan->features()
            ->where('feature_key', $featureKey)
            ->exists();
    }

    /**
     * Get a specific feature for a subscription.
     */
    protected function getFeature(UserSubscription $subscription, string $featureKey): ?PlanFeature
    {
        return $subscription->plan->features()
            ->where('feature_key', $featureKey)
            ->first();
    }

    /**
     * Build features list for a subscription.
     */
    protected function buildFeaturesList(UserSubscription $subscription): array
    {
        return $subscription->plan->features->map(function ($feature) {
            return [
                'key' => $feature->feature_key,
                'name' => $feature->feature_name,
                'type' => $feature->feature_type,
                'limit' => $feature->typed_limit,
                'human_limit' => $feature->human_limit,
                'is_unlimited' => $feature->is_unlimited,
                'description' => $feature->description,
                'is_enabled' => $feature->feature_type === 'boolean' ? (bool) $feature->feature_limit : true,
            ];
        })->toArray();
    }
}
