<?php

namespace RiaanZA\LaravelSubscription\Tests\Unit\Services;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Services\SubscriptionService;
use RiaanZA\LaravelSubscription\Tests\TestCase;
use RiaanZA\LaravelSubscription\Exceptions\SubscriptionException;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionService = app(SubscriptionService::class);
    }

    /** @test */
    public function it_can_create_a_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();

        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        $this->assertInstanceOf(UserSubscription::class, $subscription);
        $this->assertEquals($user->id, $subscription->user_id);
        $this->assertEquals($plan->id, $subscription->plan_id);
        $this->assertEquals('active', $subscription->status);
    }

    /** @test */
    public function it_can_create_a_trial_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create(['trial_days' => 14]);

        $subscription = $this->subscriptionService->createSubscription($user, $plan, [], true);

        $this->assertEquals('trial', $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertEquals(14, $subscription->trial_ends_at->diffInDays(now()));
    }

    /** @test */
    public function it_throws_exception_when_user_already_has_active_subscription()
    {
        $user = $this->createUser();
        $plan1 = SubscriptionPlan::factory()->create();
        $plan2 = SubscriptionPlan::factory()->create();

        $this->subscriptionService->createSubscription($user, $plan1);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('User already has an active subscription');

        $this->subscriptionService->createSubscription($user, $plan2);
    }

    /** @test */
    public function it_can_cancel_a_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        $cancelledSubscription = $this->subscriptionService->cancelSubscription($subscription);

        $this->assertEquals('cancelled', $cancelledSubscription->status);
        $this->assertNotNull($cancelledSubscription->cancelled_at);
    }

    /** @test */
    public function it_can_cancel_subscription_immediately()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        $cancelledSubscription = $this->subscriptionService->cancelSubscription($subscription, true);

        $this->assertEquals('cancelled', $cancelledSubscription->status);
        $this->assertTrue($cancelledSubscription->ends_at->isPast());
    }

    /** @test */
    public function it_can_resume_a_cancelled_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);
        $this->subscriptionService->cancelSubscription($subscription);

        $resumedSubscription = $this->subscriptionService->resumeSubscription($subscription);

        $this->assertEquals('active', $resumedSubscription->status);
        $this->assertNull($resumedSubscription->cancelled_at);
    }

    /** @test */
    public function it_throws_exception_when_resuming_non_cancelled_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Subscription is not cancelled');

        $this->subscriptionService->resumeSubscription($subscription);
    }

    /** @test */
    public function it_can_change_subscription_plan()
    {
        $user = $this->createUser();
        $oldPlan = SubscriptionPlan::factory()->create(['price' => 10.00]);
        $newPlan = SubscriptionPlan::factory()->create(['price' => 20.00]);
        $subscription = $this->subscriptionService->createSubscription($user, $oldPlan);

        $updatedSubscription = $this->subscriptionService->changePlan($subscription, $newPlan);

        $this->assertEquals($newPlan->id, $updatedSubscription->plan_id);
    }

    /** @test */
    public function it_can_renew_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create(['billing_cycle' => 'monthly']);
        $subscription = $this->subscriptionService->createSubscription($user, $plan);
        $originalEndsAt = $subscription->ends_at;

        $renewedSubscription = $this->subscriptionService->renewSubscription($subscription);

        $this->assertTrue($renewedSubscription->ends_at->gt($originalEndsAt));
    }

    /** @test */
    public function it_can_extend_trial_period()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create(['trial_days' => 14]);
        $subscription = $this->subscriptionService->createSubscription($user, $plan, [], true);
        $originalTrialEnd = $subscription->trial_ends_at;

        $extendedSubscription = $this->subscriptionService->extendTrial($subscription, 7);

        $this->assertEquals(7, $extendedSubscription->trial_ends_at->diffInDays($originalTrialEnd));
    }

    /** @test */
    public function it_throws_exception_when_extending_non_trial_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Subscription is not on trial');

        $this->subscriptionService->extendTrial($subscription, 7);
    }

    /** @test */
    public function it_can_convert_trial_to_paid()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create(['trial_days' => 14]);
        $subscription = $this->subscriptionService->createSubscription($user, $plan, [], true);

        $convertedSubscription = $this->subscriptionService->convertTrialToPaid($subscription);

        $this->assertEquals('active', $convertedSubscription->status);
        $this->assertNull($convertedSubscription->trial_ends_at);
    }

    /** @test */
    public function it_can_pause_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        $pausedSubscription = $this->subscriptionService->pauseSubscription($subscription);

        $this->assertEquals('paused', $pausedSubscription->status);
        $this->assertNotNull($pausedSubscription->paused_at);
    }

    /** @test */
    public function it_can_unpause_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);
        $this->subscriptionService->pauseSubscription($subscription);

        $unpausedSubscription = $this->subscriptionService->unpauseSubscription($subscription);

        $this->assertEquals('active', $unpausedSubscription->status);
        $this->assertNull($unpausedSubscription->paused_at);
    }

    /** @test */
    public function it_can_get_subscription_status()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        $status = $this->subscriptionService->getSubscriptionStatus($user);

        $this->assertIsArray($status);
        $this->assertEquals('active', $status['status']);
        $this->assertEquals($plan->name, $status['plan_name']);
    }

    /** @test */
    public function it_returns_no_subscription_status_for_user_without_subscription()
    {
        $user = $this->createUser();

        $status = $this->subscriptionService->getSubscriptionStatus($user);

        $this->assertIsArray($status);
        $this->assertEquals('none', $status['status']);
        $this->assertNull($status['plan_name']);
    }

    /** @test */
    public function it_can_check_if_user_can_subscribe_to_plan()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();

        $canSubscribe = $this->subscriptionService->canSubscribeToPlan($user, $plan);

        $this->assertTrue($canSubscribe);
    }

    /** @test */
    public function it_prevents_subscription_when_user_has_active_subscription()
    {
        $user = $this->createUser();
        $plan1 = SubscriptionPlan::factory()->create();
        $plan2 = SubscriptionPlan::factory()->create();
        $this->subscriptionService->createSubscription($user, $plan1);

        $canSubscribe = $this->subscriptionService->canSubscribeToPlan($user, $plan2);

        $this->assertFalse($canSubscribe);
    }

    /** @test */
    public function it_can_calculate_proration_amount()
    {
        $user = $this->createUser();
        $oldPlan = SubscriptionPlan::factory()->create(['price' => 10.00, 'billing_cycle' => 'monthly']);
        $newPlan = SubscriptionPlan::factory()->create(['price' => 20.00, 'billing_cycle' => 'monthly']);
        $subscription = $this->subscriptionService->createSubscription($user, $oldPlan);

        // Travel to middle of billing period
        $this->travel(15)->days();

        $proration = $this->subscriptionService->calculateProration($subscription, $newPlan);

        $this->assertIsArray($proration);
        $this->assertArrayHasKey('amount', $proration);
        $this->assertArrayHasKey('type', $proration);
    }

    /** @test */
    public function it_can_get_upcoming_invoice()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        $invoice = $this->subscriptionService->getUpcomingInvoice($subscription);

        $this->assertIsArray($invoice);
        $this->assertArrayHasKey('amount', $invoice);
        $this->assertArrayHasKey('due_date', $invoice);
    }

    /** @test */
    public function it_can_process_subscription_renewal()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create(['billing_cycle' => 'monthly']);
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        // Travel to renewal date
        $this->travel($subscription->ends_at);

        $renewedSubscription = $this->subscriptionService->processRenewal($subscription);

        $this->assertEquals('active', $renewedSubscription->status);
        $this->assertTrue($renewedSubscription->ends_at->gt($subscription->ends_at));
    }

    /** @test */
    public function it_can_handle_failed_payment()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        $updatedSubscription = $this->subscriptionService->handleFailedPayment($subscription);

        $this->assertEquals('past_due', $updatedSubscription->status);
    }

    /** @test */
    public function it_can_expire_subscription_after_grace_period()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);
        $this->subscriptionService->handleFailedPayment($subscription);

        // Travel past grace period
        $this->travel(config('laravel-subscription.grace_period_days', 3))->days();

        $expiredSubscription = $this->subscriptionService->expireSubscription($subscription);

        $this->assertEquals('expired', $expiredSubscription->status);
    }
}
