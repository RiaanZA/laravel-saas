<?php

namespace RiaanZA\LaravelSubscription\Services;

use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class SubscriptionService
{
    protected ?UsageService $usageService = null;

    /**
     * Set the usage service (to avoid circular dependency).
     */
    public function setUsageService(UsageService $usageService): void
    {
        $this->usageService = $usageService;
    }
    /**
     * Create a new subscription for a user.
     */
    public function createSubscription(
        Model $user,
        SubscriptionPlan $plan,
        array $paymentData = [],
        bool $startTrial = false
    ): UserSubscription {
        // Check if user already has an active subscription
        $existingSubscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->first();

        if ($existingSubscription) {
            throw new Exception('User already has an active subscription');
        }

        $now = now();
        $periodStart = $now;
        $periodEnd = $this->calculatePeriodEnd($periodStart, $plan->billing_cycle);

        $subscriptionData = [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => $startTrial && $plan->hasTrialPeriod() ? 'trial' : 'active',
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'amount' => $plan->price,
            'currency' => 'ZAR',
        ];

        // Set trial end date if starting trial
        if ($startTrial && $plan->hasTrialPeriod()) {
            $subscriptionData['trial_ends_at'] = $now->copy()->addDays($plan->trial_days);
        }

        $subscription = UserSubscription::create($subscriptionData);

        // Initialize usage tracking for all plan features
        $this->initializeUsageTracking($subscription);

        return $subscription;
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(
        UserSubscription $subscription,
        bool $immediately = false,
        ?string $reason = null
    ): UserSubscription {
        $data = [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ];

        if ($immediately) {
            $data['ends_at'] = now();
        } else {
            $data['ends_at'] = $subscription->current_period_end;
        }

        if ($reason) {
            $metadata = $subscription->metadata ?? [];
            $metadata['cancellation_reason'] = $reason;
            $data['metadata'] = $metadata;
        }

        $subscription->update($data);

        return $subscription;
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resumeSubscription(UserSubscription $subscription): UserSubscription
    {
        if (!$subscription->isCancelled()) {
            throw new Exception('Only cancelled subscriptions can be resumed');
        }

        if ($subscription->isExpired()) {
            throw new Exception('Cannot resume an expired subscription');
        }

        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
            'ends_at' => null,
        ]);

        return $subscription;
    }

    /**
     * Change subscription plan.
     */
    public function changePlan(
        UserSubscription $subscription,
        SubscriptionPlan $newPlan,
        bool $prorate = true
    ): UserSubscription {
        if ($subscription->plan_id === $newPlan->id) {
            throw new Exception('User is already subscribed to this plan');
        }

        $subscription->update([
            'plan_id' => $newPlan->id,
            'amount' => $newPlan->price,
        ]);

        // Re-initialize usage tracking for new plan features
        $this->initializeUsageTracking($subscription);

        return $subscription;
    }

    /**
     * Renew a subscription.
     */
    public function renewSubscription(UserSubscription $subscription): UserSubscription
    {
        $newPeriodStart = $subscription->current_period_end;
        $newPeriodEnd = $this->calculatePeriodEnd($newPeriodStart, $subscription->plan->billing_cycle);

        $subscription->update([
            'current_period_start' => $newPeriodStart,
            'current_period_end' => $newPeriodEnd,
            'status' => 'active',
        ]);

        // Reset usage tracking for the new period
        $this->resetUsageForNewPeriod($subscription);

        return $subscription;
    }

    /**
     * Calculate period end date based on billing cycle.
     */
    protected function calculatePeriodEnd(Carbon $start, string $billingCycle): Carbon
    {
        return match ($billingCycle) {
            'monthly' => $start->copy()->addMonth(),
            'yearly' => $start->copy()->addYear(),
            'quarterly' => $start->copy()->addMonths(3),
            'weekly' => $start->copy()->addWeek(),
            default => $start->copy()->addMonth(),
        };
    }

    /**
     * Initialize usage tracking for all plan features.
     */
    protected function initializeUsageTracking(UserSubscription $subscription): void
    {
        if ($this->usageService) {
            $this->usageService->initializeNewPeriod($subscription);
        } else {
            // Fallback to direct implementation
            foreach ($subscription->plan->features as $feature) {
                if ($feature->feature_type === 'numeric') {
                    $subscription->usage()->updateOrCreate([
                        'feature_key' => $feature->feature_key,
                        'period_start' => $subscription->current_period_start,
                        'period_end' => $subscription->current_period_end,
                    ], [
                        'usage_count' => 0,
                    ]);
                }
            }
        }

        Log::info('Usage tracking initialized for subscription', [
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'period_start' => $subscription->current_period_start->toDateString(),
            'period_end' => $subscription->current_period_end->toDateString(),
        ]);
    }

    /**
     * Reset usage tracking for a new billing period.
     */
    protected function resetUsageForNewPeriod(UserSubscription $subscription): void
    {
        if ($this->usageService) {
            $this->usageService->initializeNewPeriod($subscription);
        } else {
            // Fallback to direct implementation
            foreach ($subscription->plan->features as $feature) {
                if ($feature->feature_type === 'numeric') {
                    $subscription->usage()->create([
                        'feature_key' => $feature->feature_key,
                        'period_start' => $subscription->current_period_start,
                        'period_end' => $subscription->current_period_end,
                        'usage_count' => 0,
                    ]);
                }
            }
        }

        Log::info('Usage tracking reset for new billing period', [
            'subscription_id' => $subscription->id,
            'period_start' => $subscription->current_period_start->toDateString(),
            'period_end' => $subscription->current_period_end->toDateString(),
        ]);
    }
}
