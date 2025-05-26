<?php

namespace RiaanZA\LaravelSubscription\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

trait AuthorizationHelpers
{
    /**
     * Check if user has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return Gate::allows('has-active-subscription');
    }

    /**
     * Check if user is on trial.
     */
    public function onTrial(): bool
    {
        return Gate::allows('on-trial');
    }

    /**
     * Check if user's subscription is ending soon.
     */
    public function subscriptionEndingSoon(): bool
    {
        return Gate::allows('subscription-ending-soon');
    }

    /**
     * Check if user has a premium plan.
     */
    public function hasPremiumPlan(): bool
    {
        return Gate::allows('has-premium-plan');
    }

    /**
     * Check if user has an enterprise plan.
     */
    public function hasEnterprisePlan(): bool
    {
        return Gate::allows('has-enterprise-plan');
    }

    /**
     * Check if user can use a specific feature.
     */
    public function canUseFeatureGate(string $featureKey, int $increment = 1): bool
    {
        return Gate::allows('can-use-feature', [$featureKey, $increment]);
    }

    /**
     * Check if user can access a specific feature.
     */
    public function canAccessFeature(string $feature): bool
    {
        return Gate::allows("access-{$feature}");
    }

    /**
     * Check if user is a subscription admin.
     */
    public function isSubscriptionAdmin(): bool
    {
        return Gate::allows('is-subscription-admin');
    }

    /**
     * Check if user can access API features.
     */
    public function canAccessApi(): bool
    {
        return $this->canAccessFeature('api_access');
    }

    /**
     * Check if user can access advanced analytics.
     */
    public function canAccessAdvancedAnalytics(): bool
    {
        return $this->canAccessFeature('advanced_analytics');
    }

    /**
     * Check if user can access priority support.
     */
    public function canAccessPrioritySupport(): bool
    {
        return $this->canAccessFeature('priority_support');
    }

    /**
     * Check if user can access phone support.
     */
    public function canAccessPhoneSupport(): bool
    {
        return $this->canAccessFeature('phone_support');
    }

    /**
     * Check if user can access white label features.
     */
    public function canAccessWhiteLabel(): bool
    {
        return $this->canAccessFeature('white_label');
    }

    /**
     * Check if user can access SSO integration.
     */
    public function canAccessSso(): bool
    {
        return $this->canAccessFeature('sso_integration');
    }

    /**
     * Check if user can access custom branding.
     */
    public function canAccessCustomBranding(): bool
    {
        return $this->canAccessFeature('custom_branding');
    }

    /**
     * Check if user has a dedicated account manager.
     */
    public function hasDedicatedAccountManager(): bool
    {
        return $this->canAccessFeature('dedicated_manager');
    }

    /**
     * Get all accessible features for the user.
     */
    public function getAccessibleFeatures(): array
    {
        $features = config('laravel-subscription.authorization.feature_gates', []);
        $accessible = [];

        foreach (array_keys($features) as $feature) {
            if ($this->canAccessFeature($feature)) {
                $accessible[] = $feature;
            }
        }

        return $accessible;
    }

    /**
     * Check multiple feature access at once.
     */
    public function canAccessFeatures(array $features): array
    {
        $results = [];

        foreach ($features as $feature) {
            $results[$feature] = $this->canAccessFeature($feature);
        }

        return $results;
    }

    /**
     * Check if user has any of the specified features.
     */
    public function hasAnyFeature(array $features): bool
    {
        foreach ($features as $feature) {
            if ($this->canAccessFeature($feature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the specified features.
     */
    public function hasAllFeatures(array $features): bool
    {
        foreach ($features as $feature) {
            if (!$this->canAccessFeature($feature)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user's subscription tier based on plan.
     */
    public function getSubscriptionTier(): string
    {
        if ($this->hasEnterprisePlan()) {
            return 'enterprise';
        }

        if ($this->hasPremiumPlan()) {
            return 'premium';
        }

        if ($this->hasActiveSubscription()) {
            return 'basic';
        }

        return 'none';
    }

    /**
     * Check if user can perform subscription management actions.
     */
    public function canManageSubscription(): bool
    {
        return $this->hasActiveSubscription();
    }

    /**
     * Check if user can upgrade their subscription.
     */
    public function canUpgradeSubscription(): bool
    {
        return $this->hasActiveSubscription() && !$this->hasEnterprisePlan();
    }

    /**
     * Check if user can downgrade their subscription.
     */
    public function canDowngradeSubscription(): bool
    {
        return $this->hasActiveSubscription() && ($this->hasPremiumPlan() || $this->hasEnterprisePlan());
    }

    /**
     * Get subscription status with authorization context.
     */
    public function getSubscriptionStatusWithAuth(): array
    {
        $status = $this->getSubscriptionStatus();
        
        $status['authorization'] = [
            'can_manage' => $this->canManageSubscription(),
            'can_upgrade' => $this->canUpgradeSubscription(),
            'can_downgrade' => $this->canDowngradeSubscription(),
            'tier' => $this->getSubscriptionTier(),
            'accessible_features' => $this->getAccessibleFeatures(),
        ];

        return $status;
    }
}
