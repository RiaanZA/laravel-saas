<?php

namespace RiaanZA\LaravelSubscription\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use RiaanZA\LaravelSubscription\Models\PlanFeature;

class FeaturePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any features.
     */
    public function viewAny($user): bool
    {
        // All authenticated users can view features
        return true;
    }

    /**
     * Determine whether the user can view the feature.
     */
    public function view($user, PlanFeature $feature): bool
    {
        // Admin users can view any feature
        if ($this->isAdmin($user)) {
            return true;
        }

        // Users can view features of active plans
        return $feature->plan->is_active;
    }

    /**
     * Determine whether the user can create features.
     */
    public function create($user): bool
    {
        // Only admin users can create features
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can update the feature.
     */
    public function update($user, PlanFeature $feature): bool
    {
        // Only admin users can update features
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can delete the feature.
     */
    public function delete($user, PlanFeature $feature): bool
    {
        // Only admin users can delete features
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can restore the feature.
     */
    public function restore($user, PlanFeature $feature): bool
    {
        // Only admin users can restore features
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can permanently delete the feature.
     */
    public function forceDelete($user, PlanFeature $feature): bool
    {
        // Only admin users can force delete features
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can use the feature.
     */
    public function use($user, PlanFeature $feature): bool
    {
        // Admin users can use any feature
        if ($this->isAdmin($user)) {
            return true;
        }

        // Check if user has an active subscription
        if (!$user->hasActiveSubscription()) {
            return false;
        }

        // Check if the user's subscription includes this feature
        $subscription = $user->activeSubscription();
        return $subscription->plan->features()->where('id', $feature->id)->exists();
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
