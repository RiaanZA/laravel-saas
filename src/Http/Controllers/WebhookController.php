<?php

namespace RiaanZA\LaravelSubscription\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Services\PaymentService;
use RiaanZA\LaravelSubscription\Services\SubscriptionService;

class WebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Handle Peach Payments webhooks.
     */
    public function peachPayments(Request $request): JsonResponse
    {
        try {
            // Verify webhook signature
            if (!$this->verifyWebhookSignature($request)) {
                Log::warning('Invalid webhook signature from Peach Payments', [
                    'ip' => $request->ip(),
                    'headers' => $request->headers->all(),
                ]);
                
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $payload = $request->all();
            $eventType = $payload['event_type'] ?? null;
            
            Log::info('Peach Payments webhook received', [
                'event_type' => $eventType,
                'payload' => $payload,
            ]);

            switch ($eventType) {
                case 'subscription.created':
                    return $this->handleSubscriptionCreated($payload);
                
                case 'subscription.updated':
                    return $this->handleSubscriptionUpdated($payload);
                
                case 'subscription.cancelled':
                    return $this->handleSubscriptionCancelled($payload);
                
                case 'payment.succeeded':
                    return $this->handlePaymentSucceeded($payload);
                
                case 'payment.failed':
                    return $this->handlePaymentFailed($payload);
                
                case 'invoice.payment_succeeded':
                    return $this->handleInvoicePaymentSucceeded($payload);
                
                case 'invoice.payment_failed':
                    return $this->handleInvoicePaymentFailed($payload);
                
                default:
                    Log::info('Unhandled webhook event type', [
                        'event_type' => $eventType,
                        'payload' => $payload,
                    ]);
                    
                    return response()->json(['message' => 'Event type not handled'], 200);
            }

        } catch (\Exception $e) {
            Log::error('Error processing Peach Payments webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle subscription created webhook.
     */
    protected function handleSubscriptionCreated(array $payload): JsonResponse
    {
        $peachSubscriptionId = $payload['subscription']['id'] ?? null;
        $customerId = $payload['subscription']['customer_id'] ?? null;

        if (!$peachSubscriptionId || !$customerId) {
            return response()->json(['error' => 'Missing required data'], 400);
        }

        try {
            // Find the subscription by customer ID or other identifier
            $subscription = UserSubscription::where('peach_subscription_id', $peachSubscriptionId)
                ->orWhere('metadata->customer_id', $customerId)
                ->first();

            if ($subscription) {
                $subscription->update([
                    'peach_subscription_id' => $peachSubscriptionId,
                    'status' => 'active',
                ]);

                Log::info('Subscription activated via webhook', [
                    'subscription_id' => $subscription->id,
                    'peach_subscription_id' => $peachSubscriptionId,
                ]);
            }

            return response()->json(['message' => 'Subscription created processed'], 200);

        } catch (\Exception $e) {
            Log::error('Error handling subscription created webhook', [
                'error' => $e->getMessage(),
                'peach_subscription_id' => $peachSubscriptionId,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle subscription updated webhook.
     */
    protected function handleSubscriptionUpdated(array $payload): JsonResponse
    {
        $peachSubscriptionId = $payload['subscription']['id'] ?? null;
        $status = $payload['subscription']['status'] ?? null;

        if (!$peachSubscriptionId) {
            return response()->json(['error' => 'Missing subscription ID'], 400);
        }

        try {
            $subscription = UserSubscription::where('peach_subscription_id', $peachSubscriptionId)->first();

            if ($subscription) {
                $updateData = [];

                // Map Peach status to our status
                if ($status) {
                    $updateData['status'] = $this->mapPeachStatusToLocal($status);
                }

                if (!empty($updateData)) {
                    $subscription->update($updateData);
                }

                Log::info('Subscription updated via webhook', [
                    'subscription_id' => $subscription->id,
                    'peach_subscription_id' => $peachSubscriptionId,
                    'status' => $status,
                ]);
            }

            return response()->json(['message' => 'Subscription updated processed'], 200);

        } catch (\Exception $e) {
            Log::error('Error handling subscription updated webhook', [
                'error' => $e->getMessage(),
                'peach_subscription_id' => $peachSubscriptionId,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle subscription cancelled webhook.
     */
    protected function handleSubscriptionCancelled(array $payload): JsonResponse
    {
        $peachSubscriptionId = $payload['subscription']['id'] ?? null;

        if (!$peachSubscriptionId) {
            return response()->json(['error' => 'Missing subscription ID'], 400);
        }

        try {
            $subscription = UserSubscription::where('peach_subscription_id', $peachSubscriptionId)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'ends_at' => $subscription->current_period_end,
                ]);

                Log::info('Subscription cancelled via webhook', [
                    'subscription_id' => $subscription->id,
                    'peach_subscription_id' => $peachSubscriptionId,
                ]);
            }

            return response()->json(['message' => 'Subscription cancelled processed'], 200);

        } catch (\Exception $e) {
            Log::error('Error handling subscription cancelled webhook', [
                'error' => $e->getMessage(),
                'peach_subscription_id' => $peachSubscriptionId,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle payment succeeded webhook.
     */
    protected function handlePaymentSucceeded(array $payload): JsonResponse
    {
        $paymentId = $payload['payment']['id'] ?? null;
        $subscriptionId = $payload['payment']['subscription_id'] ?? null;

        try {
            if ($subscriptionId) {
                $subscription = UserSubscription::where('peach_subscription_id', $subscriptionId)->first();

                if ($subscription && $subscription->status === 'past_due') {
                    $subscription->update(['status' => 'active']);
                }
            }

            Log::info('Payment succeeded webhook processed', [
                'payment_id' => $paymentId,
                'subscription_id' => $subscriptionId,
            ]);

            return response()->json(['message' => 'Payment succeeded processed'], 200);

        } catch (\Exception $e) {
            Log::error('Error handling payment succeeded webhook', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle payment failed webhook.
     */
    protected function handlePaymentFailed(array $payload): JsonResponse
    {
        $paymentId = $payload['payment']['id'] ?? null;
        $subscriptionId = $payload['payment']['subscription_id'] ?? null;

        try {
            if ($subscriptionId) {
                $subscription = UserSubscription::where('peach_subscription_id', $subscriptionId)->first();

                if ($subscription) {
                    $subscription->update(['status' => 'past_due']);
                }
            }

            Log::info('Payment failed webhook processed', [
                'payment_id' => $paymentId,
                'subscription_id' => $subscriptionId,
            ]);

            return response()->json(['message' => 'Payment failed processed'], 200);

        } catch (\Exception $e) {
            Log::error('Error handling payment failed webhook', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle invoice payment succeeded webhook.
     */
    protected function handleInvoicePaymentSucceeded(array $payload): JsonResponse
    {
        $subscriptionId = $payload['invoice']['subscription_id'] ?? null;

        try {
            if ($subscriptionId) {
                $subscription = UserSubscription::where('peach_subscription_id', $subscriptionId)->first();

                if ($subscription) {
                    // Renew subscription for next period
                    $this->subscriptionService->renewSubscription($subscription);
                }
            }

            Log::info('Invoice payment succeeded webhook processed', [
                'subscription_id' => $subscriptionId,
            ]);

            return response()->json(['message' => 'Invoice payment succeeded processed'], 200);

        } catch (\Exception $e) {
            Log::error('Error handling invoice payment succeeded webhook', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle invoice payment failed webhook.
     */
    protected function handleInvoicePaymentFailed(array $payload): JsonResponse
    {
        $subscriptionId = $payload['invoice']['subscription_id'] ?? null;

        try {
            if ($subscriptionId) {
                $subscription = UserSubscription::where('peach_subscription_id', $subscriptionId)->first();

                if ($subscription) {
                    $subscription->update(['status' => 'past_due']);
                }
            }

            Log::info('Invoice payment failed webhook processed', [
                'subscription_id' => $subscriptionId,
            ]);

            return response()->json(['message' => 'Invoice payment failed processed'], 200);

        } catch (\Exception $e) {
            Log::error('Error handling invoice payment failed webhook', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Verify webhook signature.
     */
    protected function verifyWebhookSignature(Request $request): bool
    {
        // Implement signature verification based on Peach Payments documentation
        // This is a placeholder - you'll need to implement the actual verification
        $signature = $request->header('X-Peach-Signature');
        $payload = $request->getContent();
        
        // For now, return true - implement actual verification based on Peach Payments docs
        return true;
    }

    /**
     * Map Peach Payments status to local status.
     */
    protected function mapPeachStatusToLocal(string $peachStatus): string
    {
        return match (strtolower($peachStatus)) {
            'active' => 'active',
            'cancelled', 'canceled' => 'cancelled',
            'past_due' => 'past_due',
            'suspended' => 'suspended',
            'expired' => 'expired',
            default => 'pending',
        };
    }
}
