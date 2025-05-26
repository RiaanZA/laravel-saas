<?php

namespace RiaanZA\LaravelSubscription\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Services\PaymentService;
use RiaanZA\LaravelSubscription\Http\Requests\ProcessPaymentRequest;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Show the checkout page for a subscription plan.
     */
    public function checkout(Request $request, string $planSlug): Response
    {
        $plan = SubscriptionPlan::where('slug', $planSlug)
            ->active()
            ->with('features')
            ->firstOrFail();

        $user = $request->user();
        
        // Check if user already has an active subscription
        $existingSubscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->first();

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

        return Inertia::render('Subscription/Checkout', [
            'plan' => $planData,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'existing_subscription' => $existingSubscription ? [
                'id' => $existingSubscription->id,
                'plan_name' => $existingSubscription->plan->name,
                'status' => $existingSubscription->status,
            ] : null,
            'payment_config' => [
                'currency' => config('laravel-subscription.ui.currency_code', 'ZAR'),
                'return_url' => route('subscription.payment.success'),
                'cancel_url' => route('subscription.payment.cancelled'),
            ],
        ]);
    }

    /**
     * Process payment for a subscription.
     */
    public function process(ProcessPaymentRequest $request): JsonResponse
    {
        $user = $request->user();
        $plan = SubscriptionPlan::where('slug', $request->plan_slug)->firstOrFail();

        try {
            $result = $this->paymentService->processSubscriptionPayment(
                $user,
                $plan,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'payment_url' => $result['payment_url'] ?? null,
                'subscription_id' => $result['subscription_id'] ?? null,
                'redirect_url' => $result['redirect_url'] ?? route('subscription.payment.success'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Handle successful payment callback.
     */
    public function success(Request $request): Response|RedirectResponse
    {
        $paymentId = $request->get('payment_id');
        $subscriptionId = $request->get('subscription_id');

        if ($paymentId && $subscriptionId) {
            try {
                $this->paymentService->handleSuccessfulPayment($paymentId, $subscriptionId);
                
                return Inertia::render('Subscription/Success', [
                    'message' => 'Payment successful! Your subscription is now active.',
                    'subscription_id' => $subscriptionId,
                ]);
            } catch (\Exception $e) {
                return Inertia::render('Subscription/Success', [
                    'message' => 'Payment completed, but there was an issue activating your subscription. Please contact support.',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Inertia::render('Subscription/Success', [
            'message' => 'Payment completed successfully!',
        ]);
    }

    /**
     * Handle cancelled payment.
     */
    public function cancelled(Request $request): Response
    {
        return Inertia::render('Subscription/Cancelled', [
            'message' => 'Payment was cancelled. You can try again anytime.',
            'plan_slug' => $request->get('plan_slug'),
        ]);
    }

    /**
     * Handle failed payment.
     */
    public function failed(Request $request): Response
    {
        $error = $request->get('error', 'Payment failed. Please try again.');
        
        return Inertia::render('Subscription/Failed', [
            'message' => 'Payment failed. Please check your payment details and try again.',
            'error' => $error,
            'plan_slug' => $request->get('plan_slug'),
        ]);
    }

    /**
     * Get payment methods for a user.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $user = $request->user();
        
        try {
            $paymentMethods = $this->paymentService->getUserPaymentMethods($user);
            
            return response()->json([
                'payment_methods' => $paymentMethods,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve payment methods',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Add a new payment method.
     */
    public function addPaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method_data' => 'required|array',
        ]);

        $user = $request->user();
        
        try {
            $paymentMethod = $this->paymentService->addPaymentMethod(
                $user,
                $request->payment_method_data
            );
            
            return response()->json([
                'message' => 'Payment method added successfully',
                'payment_method' => $paymentMethod,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add payment method',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove a payment method.
     */
    public function removePaymentMethod(Request $request, string $paymentMethodId): JsonResponse
    {
        $user = $request->user();
        
        try {
            $this->paymentService->removePaymentMethod($user, $paymentMethodId);
            
            return response()->json([
                'message' => 'Payment method removed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove payment method',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update default payment method.
     */
    public function updateDefaultPaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $user = $request->user();
        
        try {
            $this->paymentService->updateDefaultPaymentMethod(
                $user,
                $request->payment_method_id
            );
            
            return response()->json([
                'message' => 'Default payment method updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update default payment method',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
