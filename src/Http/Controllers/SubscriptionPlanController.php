<?php

namespace RiaanZA\LaravelSubscription\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;

class SubscriptionPlanController extends Controller
{
    /**
     * Display a listing of available subscription plans.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $plans = SubscriptionPlan::active()
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
                    'features' => $plan->features->map(function ($feature) {
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
                ];
            });

        if ($request->expectsJson()) {
            return response()->json([
                'plans' => $plans,
            ]);
        }

        return Inertia::render('Subscription/Plans', [
            'plans' => $plans,
        ]);
    }

    /**
     * Display the specified subscription plan.
     */
    public function show(Request $request, string $slug): Response|JsonResponse
    {
        $plan = SubscriptionPlan::where('slug', $slug)
            ->active()
            ->with('features')
            ->firstOrFail();

        $planData = [
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
            'features' => $plan->features->map(function ($feature) {
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
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'plan' => $planData,
            ]);
        }

        return Inertia::render('Subscription/PlanDetails', [
            'plan' => $planData,
        ]);
    }
}
