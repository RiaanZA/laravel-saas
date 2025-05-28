<?php

namespace RiaanZA\LaravelSubscription\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use RiaanZA\LaravelSubscription\Models\UserSubscription;

class SubscriptionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the subscription.
     */
    public function view($user, UserSubscription $subscription): bool
    {
        // Admin users can view any subscription
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can only view their own subscriptions
        return $user->id === $subscription->user_id;
    }

    /**
     * Determine whether the user can create subscriptions.
     */
    public function create($user): bool
    {
        // Admin users can always create subscriptions
        if ($this->isAdmin($user)) {
            return true;
        }

        // Check if multiple subscriptions are allowed
        if (config('laravel-subscription.authorization.policies.allow_multiple_subscriptions', false)) {
            return true;
        }

        // Users can only create a subscription if they don't have an active one
        return !$user->hasActiveSubscription();
    }

    /**
     * Determine whether the user can update the subscription.
     */
    public function update($user, UserSubscription $subscription): bool
    {
        // Admin users can update any subscription
        if ($this->isAdmin($user)) {
            return true;
        }

        // Check if plan changes are allowed
        if (!config('laravel-subscription.authorization.policies.allow_plan_changes', true)) {
            return false;
        }

        // Users can only update their own subscriptions
        return $user->id === $subscription->user_id;
    }

    /**
     * Determine whether the user can cancel the subscription.
     */
    public function cancel($user, UserSubscription $subscription): bool
    {
        // Admin users can cancel any subscription
        if ($this->isAdmin($user)) {
            return true;
        }

        // Check if cancellation is allowed
        if (!config('laravel-subscription.authorization.policies.allow_cancellation', true)) {
            return false;
        }

        // Users can only cancel their own subscriptions
        if ($user->id !== $subscription->user_id) {
            return false;
        }

        // Can only cancel active or trial subscriptions
        return in_array($subscription->status, ['active', 'trial']);
    }

    /**
     * Determine whether the user can resume the subscription.
     */
    public function resume($user, UserSubscription $subscription): bool
    {
        // Admin users can resume any subscription
        if ($this->isAdmin($user)) {
            return true;
        }

        // Check if resumption is allowed
        if (!config('laravel-subscription.authorization.policies.allow_resumption', true)) {
            return false;
        }

        // Users can only resume their own subscriptions
        if ($user->id !== $subscription->user_id) {
            return false;
        }

        // Can only resume cancelled subscriptions that haven't expired
        return $subscription->status === 'cancelled' && 
               $subscription->ends_at && 
               $subscription->ends_at->isFuture();
    }

    /**
     * Determine whether the user can delete the subscription.
     */
    public function delete($user, UserSubscription $subscription): bool
    {
        // Admin users can delete any subscription
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can only delete their own subscriptions
        return $user->id === $subscription->user_id;
    }

    /**
     * Determine whether the user can view billing information.
     */
    public function viewBilling($user, UserSubscription $subscription): bool
    {
        // Admin users can view any billing information
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can only view their own billing information
        return $user->id === $subscription->user_id;
    }

    /**
     * Determine whether the user can export subscription data.
     */
    public function exportData($user, UserSubscription $subscription): bool
    {
        // Admin users can export any data
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can only export their own data
        return $user->id === $subscription->user_id;
    }

    /**
     * Determine whether the user can subscribe to a specific plan.
     */
    public function subscribe($user, $plan): bool
    {
        // Admin users can subscribe to any plan
        if ($this->isAdmin($user)) {
            return true;
        }

        // Check if the plan is active
        if (!$plan->is_active) {
            return false;
        }

        // Additional plan-specific authorization logic can be added here
        return true;
    }

    /**
     * Check if the user is an admin.
     */
    protected function isAdmin($user): bool
    {
        // Check admin emails from configuration
        $adminEmails = config('laravel-subscription.authorization.admin_emails', []);
        if (in_array($user->email, $adminEmails)) {
            return true;
        }

        // Check super admin emails from configuration
        $superAdminEmails = config('laravel-subscription.authorization.super_admin_emails', []);
        if (in_array($user->email, $superAdminEmails)) {
            return true;
        }

        // Check if user has admin role (if using a role system)
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin') || $user->hasRole('super-admin');
        }

        // Check if user has is_admin property
        if (property_exists($user, 'is_admin')) {
            return $user->is_admin;
        }

        return false;
    }
}
