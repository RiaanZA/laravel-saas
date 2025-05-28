<?php

namespace RiaanZA\LaravelSubscription\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use RiaanZA\LaravelSubscription\Models\SubscriptionUsage;

class UsagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any usage records.
     */
    public function viewAny($user): bool
    {
        // Admin users can view any usage records
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can view their own usage records
        return $user->hasActiveSubscription();
    }

    /**
     * Determine whether the user can view the usage record.
     */
    public function view($user, SubscriptionUsage $usage): bool
    {
        // Admin users can view any usage record
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can only view their own usage records
        return $user->id === $usage->subscription->user_id;
    }

    /**
     * Determine whether the user can create usage records.
     */
    public function create($user): bool
    {
        // Admin users can create usage records
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can create usage records for their own subscriptions
        return $user->hasActiveSubscription();
    }

    /**
     * Determine whether the user can update the usage record.
     */
    public function update($user, SubscriptionUsage $usage): bool
    {
        // Only admin users can update usage records
        if (!$this->isAdmin($user)) {
            return false;
        }

        // Check if usage overrides are allowed
        return config('laravel-subscription.authorization.policies.allow_usage_overrides', false);
    }

    /**
     * Determine whether the user can delete the usage record.
     */
    public function delete($user, SubscriptionUsage $usage): bool
    {
        // Only admin users can delete usage records
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can restore the usage record.
     */
    public function restore($user, SubscriptionUsage $usage): bool
    {
        // Only admin users can restore usage records
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can permanently delete the usage record.
     */
    public function forceDelete($user, SubscriptionUsage $usage): bool
    {
        // Only admin users can force delete usage records
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can increment usage.
     */
    public function increment($user, SubscriptionUsage $usage): bool
    {
        // Admin users can increment any usage
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can only increment their own usage
        return $user->id === $usage->subscription->user_id;
    }

    /**
     * Determine whether the user can reset usage.
     */
    public function reset($user, SubscriptionUsage $usage): bool
    {
        // Only admin users can reset usage
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can export usage data.
     */
    public function export($user, SubscriptionUsage $usage): bool
    {
        // Admin users can export any usage data
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can only export their own usage data
        return $user->id === $usage->subscription->user_id;
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
