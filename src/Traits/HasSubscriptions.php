<?php

namespace RiaanZA\LaravelSubscription\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use RiaanZA\LaravelSubscription\Models\UserSubscription;

trait HasSubscriptions
{
    /**
     * Get the user's subscriptions.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Get the user's active subscription.
     */
    public function activeSubscription(): ?UserSubscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();
    }

    /**
     * Check if user has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    /**
     * Check if user has a specific feature.
     */
    public function hasSubscriptionFeature(string $featureKey): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription ? $subscription->hasFeature($featureKey) : false;
    }

    /**
     * Get the limit for a specific feature.
     */
    public function getSubscriptionFeatureLimit(string $featureKey): mixed
    {
        $subscription = $this->activeSubscription();
        return $subscription ? $subscription->getFeatureLimit($featureKey) : null;
    }

    /**
     * Get current usage for a feature.
     */
    public function getCurrentSubscriptionUsage(string $featureKey): int
    {
        $subscription = $this->activeSubscription();
        return $subscription ? $subscription->getCurrentUsage($featureKey) : 0;
    }

    /**
     * Check if user can use a feature (has access and within limits).
     */
    public function canUseFeature(string $featureKey, int $increment = 1): bool
    {
        try {
            $featureService = app(\RiaanZA\LaravelSubscription\Services\FeatureService::class);
            return $featureService->canUseFeature($this, $featureKey, $increment);
        } catch (\Exception $e) {
            // Fallback to basic implementation
            $subscription = $this->activeSubscription();

            if (!$subscription || !$subscription->hasFeature($featureKey)) {
                return false;
            }

            $feature = $subscription->plan->features()
                ->where('feature_key', $featureKey)
                ->first();

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
                $currentUsage = $subscription->getCurrentUsage($featureKey);
                $limit = $feature->typed_limit;

                return ($currentUsage + $increment) <= $limit;
            }

            return true;
        }
    }

    /**
     * Check if user is on trial.
     */
    public function onSubscriptionTrial(): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription ? $subscription->onTrial() : false;
    }

    /**
     * Get trial days remaining.
     */
    public function getTrialDaysRemaining(): int
    {
        $subscription = $this->activeSubscription();
        return $subscription ? $subscription->trial_days_remaining : 0;
    }

    /**
     * Check if subscription is ending soon.
     */
    public function isSubscriptionEndingSoon(int $days = 7): bool
    {
        $subscription = $this->activeSubscription();
        return $subscription ? $subscription->isEndingSoon($days) : false;
    }

    /**
     * Get subscription status for display.
     */
    public function getSubscriptionStatus(): array
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return [
                'has_subscription' => false,
                'status' => 'none',
                'message' => 'No active subscription',
            ];
        }

        return [
            'has_subscription' => true,
            'status' => $subscription->status,
            'plan_name' => $subscription->plan->name,
            'is_active' => $subscription->isActive(),
            'on_trial' => $subscription->onTrial(),
            'is_cancelled' => $subscription->isCancelled(),
            'is_past_due' => $subscription->isPastDue(),
            'trial_days_remaining' => $subscription->trial_days_remaining,
            'days_remaining' => $subscription->days_remaining,
            'next_billing_date' => $subscription->next_billing_date->toDateString(),
            'formatted_amount' => $subscription->formatted_amount,
        ];
    }

    /**
     * Increment usage for a feature.
     */
    public function incrementFeatureUsage(string $featureKey, int $increment = 1): bool
    {
        try {
            $usageService = app(\RiaanZA\LaravelSubscription\Services\UsageService::class);
            return $usageService->incrementUsage($this, $featureKey, $increment);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Decrement usage for a feature.
     */
    public function decrementFeatureUsage(string $featureKey, int $decrement = 1): bool
    {
        try {
            $usageService = app(\RiaanZA\LaravelSubscription\Services\UsageService::class);
            return $usageService->decrementUsage($this, $featureKey, $decrement);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get feature usage summary.
     */
    public function getFeatureUsageSummary(): array
    {
        try {
            $featureService = app(\RiaanZA\LaravelSubscription\Services\FeatureService::class);
            return $featureService->getFeatureUsageSummary($this);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if any features are over their limits.
     */
    public function hasOverLimitFeatures(): bool
    {
        try {
            $featureService = app(\RiaanZA\LaravelSubscription\Services\FeatureService::class);
            $overLimitFeatures = $featureService->getOverLimitFeatures($this);
            return !empty($overLimitFeatures);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get features that are over their limits.
     */
    public function getOverLimitFeatures(): array
    {
        try {
            $featureService = app(\RiaanZA\LaravelSubscription\Services\FeatureService::class);
            return $featureService->getOverLimitFeatures($this);
        } catch (\Exception $e) {
            return [];
        }
    }
}
