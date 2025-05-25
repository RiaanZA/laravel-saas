<?php

namespace RiaanZA\LaravelSubscription\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Usage Limit Middleware
 * 
 * Checks if user has exceeded usage limits for specific features.
 */
class UsageLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $featureKey, ?int $increment = 1): Response
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $user = Auth::user();
        $subscription = $user->subscriptions()
            ->with(['plan.features'])
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'Active subscription required',
                'error' => 'no_subscription',
            ], 402);
        }

        // Check if feature exists in plan
        $feature = $subscription->plan->features()
            ->where('feature_key', $featureKey)
            ->first();

        if (!$feature) {
            return response()->json([
                'message' => 'Feature not available in your plan',
                'error' => 'feature_not_available',
            ], 403);
        }

        // Skip limit check for unlimited features
        if ($feature->is_unlimited) {
            return $next($request);
        }

        // Check current usage
        $currentUsage = $subscription->getCurrentUsage($featureKey);
        $limit = $feature->typed_limit;

        if (($currentUsage + $increment) > $limit) {
            return response()->json([
                'message' => 'Usage limit exceeded for this feature',
                'error' => 'usage_limit_exceeded',
                'feature' => $featureKey,
                'current_usage' => $currentUsage,
                'limit' => $limit,
                'upgrade_url' => route('subscription.plans.index'),
            ], 429); // Too Many Requests
        }

        // Add usage data to request
        $request->merge([
            'feature_usage' => [
                'feature_key' => $featureKey,
                'current_usage' => $currentUsage,
                'limit' => $limit,
                'increment' => $increment,
            ],
        ]);

        return $next($request);
    }
}
