<?php

namespace RiaanZA\LaravelSubscription\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;

class PlanPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any plans.
     */
    public function viewAny($user): bool
    {
        // All authenticated users can view plans
        return true;
    }

    /**
     * Determine whether the user can view the plan.
     */
    public function view($user, SubscriptionPlan $plan): bool
    {
        // Admin users can view any plan
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can only view active plans
        return $plan->is_active;
    }

    /**
     * Determine whether the user can create plans.
     */
    public function create($user): bool
    {
        // Only admin users can create plans
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can update the plan.
     */
    public function update($user, SubscriptionPlan $plan): bool
    {
        // Only admin users can update plans
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can delete the plan.
     */
    public function delete($user, SubscriptionPlan $plan): bool
    {
        // Only admin users can delete plans
        if (!$this->isAdmin($user)) {
            return false;
        }

        // Cannot delete plans that have active subscriptions
        return !$plan->subscriptions()->whereIn('status', ['active', 'trial'])->exists();
    }

    /**
     * Determine whether the user can restore the plan.
     */
    public function restore($user, SubscriptionPlan $plan): bool
    {
        // Only admin users can restore plans
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can permanently delete the plan.
     */
    public function forceDelete($user, SubscriptionPlan $plan): bool
    {
        // Only admin users can force delete plans
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can subscribe to the plan.
     */
    public function subscribe($user, SubscriptionPlan $plan): bool
    {
        // Admin users can subscribe to any plan
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can only subscribe to active plans
        if (!$plan->is_active) {
            return false;
        }

        // Check if user already has an active subscription and multiple subscriptions are not allowed
        if (!config('laravel-subscription.authorization.policies.allow_multiple_subscriptions', false)) {
            if ($user->hasActiveSubscription()) {
                return false;
            }
        }

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
