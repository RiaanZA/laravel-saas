<?php

namespace RiaanZA\LaravelSubscription\Services;

use Illuminate\Database\Eloquent\Model;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\PeachPayments\PeachPayments;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected PeachPayments $peachPayments;
    protected SubscriptionService $subscriptionService;

    public function __construct(
        PeachPayments $peachPayments,
        SubscriptionService $subscriptionService
    ) {
        $this->peachPayments = $peachPayments;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Process subscription payment.
     */
    public function processSubscriptionPayment(
        Model $user,
        SubscriptionPlan $plan,
        array $paymentData
    ): array {
        try {
            // Create subscription first (in pending state)
            $subscription = $this->subscriptionService->createSubscription(
                $user,
                $plan,
                $paymentData,
                $paymentData['start_trial'] ?? false
            );

            // If starting with trial, no payment needed
            if ($subscription->status === 'trial') {
                return [
                    'subscription_id' => $subscription->id,
                    'status' => 'trial_started',
                    'redirect_url' => route('subscription.dashboard'),
                ];
            }

            // Process payment with Peach Payments
            $paymentResult = $this->createPeachPayment($user, $plan, $paymentData, $subscription);

            // Update subscription with payment details
            $subscription->update([
                'peach_subscription_id' => $paymentResult['subscription_id'] ?? null,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'payment_id' => $paymentResult['payment_id'] ?? null,
                    'customer_id' => $paymentResult['customer_id'] ?? null,
                ]),
            ]);

            return [
                'subscription_id' => $subscription->id,
                'payment_id' => $paymentResult['payment_id'] ?? null,
                'payment_url' => $paymentResult['payment_url'] ?? null,
                'redirect_url' => $paymentResult['redirect_url'] ?? route('subscription.payment.success'),
                'status' => 'payment_created',
            ];

        } catch (Exception $e) {
            Log::error('Payment processing failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Payment processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Create payment with Peach Payments.
     */
    protected function createPeachPayment(
        Model $user,
        SubscriptionPlan $plan,
        array $paymentData,
        UserSubscription $subscription
    ): array {
        $customer = $this->getOrCreateCustomer($user, $paymentData);
        
        $paymentMethodId = $this->getOrCreatePaymentMethod(
            $customer['id'],
            $paymentData
        );

        // Create subscription with Peach Payments
        $subscriptionData = [
            'customer_id' => $customer['id'],
            'payment_method_id' => $paymentMethodId,
            'plan_id' => $this->getPeachPlanId($plan),
            'amount' => $plan->price * 100, // Convert to cents
            'currency' => config('laravel-subscription.ui.currency_code', 'ZAR'),
            'billing_cycle' => $plan->billing_cycle,
            'metadata' => [
                'local_subscription_id' => $subscription->id,
                'local_user_id' => $user->id,
                'local_plan_id' => $plan->id,
            ],
            'return_url' => route('subscription.payment.success', [
                'subscription_id' => $subscription->id,
            ]),
            'cancel_url' => route('subscription.payment.cancelled', [
                'plan_slug' => $plan->slug,
            ]),
        ];

        $peachSubscription = $this->peachPayments->subscriptions()->create($subscriptionData);

        return [
            'subscription_id' => $peachSubscription['id'] ?? null,
            'payment_id' => $peachSubscription['latest_payment']['id'] ?? null,
            'customer_id' => $customer['id'],
            'payment_url' => $peachSubscription['payment_url'] ?? null,
            'redirect_url' => $peachSubscription['redirect_url'] ?? null,
        ];
    }

    /**
     * Get or create customer in Peach Payments.
     */
    protected function getOrCreateCustomer(Model $user, array $paymentData): array
    {
        // Try to find existing customer
        $existingCustomer = $this->peachPayments->customers()->search([
            'email' => $user->email,
        ]);

        if (!empty($existingCustomer['data'])) {
            return $existingCustomer['data'][0];
        }

        // Create new customer
        $customerData = [
            'email' => $user->email,
            'name' => $paymentData['billing_details']['name'] ?? $user->name,
            'phone' => $paymentData['billing_details']['phone'] ?? null,
            'address' => $paymentData['billing_address'] ?? [],
            'metadata' => [
                'local_user_id' => $user->id,
            ],
        ];

        return $this->peachPayments->customers()->create($customerData);
    }

    /**
     * Get or create payment method.
     */
    protected function getOrCreatePaymentMethod(string $customerId, array $paymentData): string
    {
        // If payment method ID is provided, use it
        if (!empty($paymentData['payment_method_id'])) {
            return $paymentData['payment_method_id'];
        }

        // Create new payment method
        if (!empty($paymentData['payment_method'])) {
            $paymentMethodData = array_merge($paymentData['payment_method'], [
                'customer_id' => $customerId,
            ]);

            $paymentMethod = $this->peachPayments->paymentMethods()->create($paymentMethodData);
            return $paymentMethod['id'];
        }

        throw new Exception('No payment method provided');
    }

    /**
     * Get Peach plan ID for local plan.
     */
    protected function getPeachPlanId(SubscriptionPlan $plan): string
    {
        // This should map local plans to Peach plan IDs
        // You might store this in plan metadata or have a separate mapping
        $metadata = $plan->metadata ?? [];
        
        if (isset($metadata['peach_plan_id'])) {
            return $metadata['peach_plan_id'];
        }

        // Create plan in Peach if it doesn't exist
        return $this->createPeachPlan($plan);
    }

    /**
     * Create plan in Peach Payments.
     */
    protected function createPeachPlan(SubscriptionPlan $plan): string
    {
        $planData = [
            'name' => $plan->name,
            'amount' => $plan->price * 100, // Convert to cents
            'currency' => config('laravel-subscription.ui.currency_code', 'ZAR'),
            'interval' => $this->mapBillingCycleToInterval($plan->billing_cycle),
            'trial_period_days' => $plan->trial_days,
            'metadata' => [
                'local_plan_id' => $plan->id,
                'local_plan_slug' => $plan->slug,
            ],
        ];

        $peachPlan = $this->peachPayments->plans()->create($planData);
        
        // Update local plan with Peach plan ID
        $metadata = $plan->metadata ?? [];
        $metadata['peach_plan_id'] = $peachPlan['id'];
        $plan->update(['metadata' => $metadata]);

        return $peachPlan['id'];
    }

    /**
     * Map billing cycle to Peach interval.
     */
    protected function mapBillingCycleToInterval(string $billingCycle): string
    {
        return match ($billingCycle) {
            'monthly' => 'month',
            'yearly' => 'year',
            'quarterly' => 'quarter',
            'weekly' => 'week',
            default => 'month',
        };
    }

    /**
     * Handle successful payment.
     */
    public function handleSuccessfulPayment(string $paymentId, string $subscriptionId): void
    {
        $subscription = UserSubscription::findOrFail($subscriptionId);
        
        // Update subscription status
        $subscription->update([
            'status' => 'active',
        ]);

        Log::info('Subscription activated after successful payment', [
            'subscription_id' => $subscriptionId,
            'payment_id' => $paymentId,
        ]);
    }

    /**
     * Get user's payment methods.
     */
    public function getUserPaymentMethods(Model $user): array
    {
        try {
            $customer = $this->peachPayments->customers()->search([
                'email' => $user->email,
            ]);

            if (empty($customer['data'])) {
                return [];
            }

            $customerId = $customer['data'][0]['id'];
            $paymentMethods = $this->peachPayments->paymentMethods()->list([
                'customer_id' => $customerId,
            ]);

            return $paymentMethods['data'] ?? [];

        } catch (Exception $e) {
            Log::error('Failed to retrieve payment methods', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to retrieve payment methods');
        }
    }

    /**
     * Add payment method for user.
     */
    public function addPaymentMethod(Model $user, array $paymentMethodData): array
    {
        try {
            $customer = $this->getOrCreateCustomer($user, [
                'billing_details' => ['name' => $user->name],
                'billing_address' => [],
            ]);

            $paymentMethodData['customer_id'] = $customer['id'];
            
            return $this->peachPayments->paymentMethods()->create($paymentMethodData);

        } catch (Exception $e) {
            Log::error('Failed to add payment method', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to add payment method');
        }
    }

    /**
     * Remove payment method.
     */
    public function removePaymentMethod(Model $user, string $paymentMethodId): void
    {
        try {
            $this->peachPayments->paymentMethods()->delete($paymentMethodId);

        } catch (Exception $e) {
            Log::error('Failed to remove payment method', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to remove payment method');
        }
    }

    /**
     * Update default payment method.
     */
    public function updateDefaultPaymentMethod(Model $user, string $paymentMethodId): void
    {
        try {
            $customer = $this->peachPayments->customers()->search([
                'email' => $user->email,
            ]);

            if (empty($customer['data'])) {
                throw new Exception('Customer not found');
            }

            $customerId = $customer['data'][0]['id'];
            
            $this->peachPayments->customers()->update($customerId, [
                'default_payment_method' => $paymentMethodId,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to update default payment method', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to update default payment method');
        }
    }
}
