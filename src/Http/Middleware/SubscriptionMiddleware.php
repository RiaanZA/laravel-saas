<?php

namespace RiaanZA\LaravelSubscription\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use RiaanZA\LaravelSubscription\Models\UserSubscription;

class SubscriptionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$parameters): Response
    {
        if (!Auth::check()) {
            return $this->redirectToLogin($request);
        }

        $user = Auth::user();
        $subscription = $this->getUserActiveSubscription($user);

        // Parse middleware parameters
        $requiredFeature = $parameters[0] ?? null;
        $requireActive = in_array('active', $parameters);
        $allowTrial = in_array('trial', $parameters);
        $allowGracePeriod = in_array('grace', $parameters);

        // Check if user has any subscription
        if (!$subscription) {
            return $this->handleNoSubscription($request);
        }

        // Check subscription status
        if ($requireActive && !$this->isSubscriptionValid($subscription, $allowTrial, $allowGracePeriod)) {
            return $this->handleInvalidSubscription($request, $subscription);
        }

        // Check specific feature access
        if ($requiredFeature && !$this->hasFeatureAccess($subscription, $requiredFeature)) {
            return $this->handleFeatureNotAvailable($request, $requiredFeature);
        }

        // Add subscription data to request
        $request->merge([
            'user_subscription' => $subscription,
        ]);

        return $next($request);
    }

    /**
     * Get user's active subscription.
     */
    protected function getUserActiveSubscription($user): ?UserSubscription
    {
        return $user->subscriptions()
            ->with(['plan.features'])
            ->whereIn('status', ['active', 'trial', 'cancelled', 'past_due'])
            ->latest()
            ->first();
    }

    /**
     * Check if subscription is valid based on parameters.
     */
    protected function isSubscriptionValid(UserSubscription $subscription, bool $allowTrial, bool $allowGracePeriod): bool
    {
        // Active subscription is always valid
        if ($subscription->isActive()) {
            return true;
        }

        // Check trial status
        if ($allowTrial && $subscription->onTrial()) {
            return true;
        }

        // Check grace period for cancelled subscriptions
        if ($allowGracePeriod && $subscription->isCancelled() && !$subscription->isExpired()) {
            return true;
        }

        // Check grace period for past due subscriptions
        if ($allowGracePeriod && $subscription->isPastDue()) {
            $gracePeriodDays = config('laravel-subscription.features.grace_period_days', 3);
            $gracePeriodEnd = $subscription->current_period_end->addDays($gracePeriodDays);
            
            if (now()->lte($gracePeriodEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if subscription has access to a specific feature.
     */
    protected function hasFeatureAccess(UserSubscription $subscription, string $featureKey): bool
    {
        return $subscription->hasFeature($featureKey);
    }

    /**
     * Handle case where user has no subscription.
     */
    protected function handleNoSubscription(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Subscription required',
                'error' => 'no_subscription',
                'redirect_url' => route('subscription.plans.index'),
            ], 402); // Payment Required
        }

        return redirect()
            ->route('subscription.plans.index')
            ->with('warning', 'Please select a subscription plan to continue.');
    }

    /**
     * Handle case where subscription is invalid.
     */
    protected function handleInvalidSubscription(Request $request, UserSubscription $subscription): Response
    {
        $message = $this->getSubscriptionStatusMessage($subscription);
        $redirectUrl = $this->getSubscriptionRedirectUrl($subscription);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => 'invalid_subscription',
                'subscription_status' => $subscription->status,
                'redirect_url' => $redirectUrl,
            ], 402); // Payment Required
        }

        return redirect($redirectUrl)->with('warning', $message);
    }

    /**
     * Handle case where feature is not available.
     */
    protected function handleFeatureNotAvailable(Request $request, string $featureKey): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This feature is not available in your current plan',
                'error' => 'feature_not_available',
                'feature' => $featureKey,
                'redirect_url' => route('subscription.plans.index'),
            ], 403); // Forbidden
        }

        return redirect()
            ->route('subscription.plans.index')
            ->with('warning', 'This feature is not available in your current plan. Please upgrade to continue.');
    }

    /**
     * Redirect to login.
     */
    protected function redirectToLogin(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Authentication required',
                'error' => 'unauthenticated',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Get appropriate message for subscription status.
     */
    protected function getSubscriptionStatusMessage(UserSubscription $subscription): string
    {
        return match ($subscription->status) {
            'cancelled' => 'Your subscription has been cancelled. Please renew to continue.',
            'expired' => 'Your subscription has expired. Please renew to continue.',
            'past_due' => 'Your subscription payment is past due. Please update your payment method.',
            'suspended' => 'Your subscription has been suspended. Please contact support.',
            default => 'Your subscription is not active. Please check your subscription status.',
        };
    }

    /**
     * Get appropriate redirect URL for subscription status.
     */
    protected function getSubscriptionRedirectUrl(UserSubscription $subscription): string
    {
        return match ($subscription->status) {
            'cancelled', 'expired' => route('subscription.plans.index'),
            'past_due' => route('subscription.dashboard'),
            default => route('subscription.dashboard'),
        };
    }
}
