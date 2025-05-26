<?php

namespace RiaanZA\LaravelSubscription\Tests\Unit\Models;

use Carbon\Carbon;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\SubscriptionUsage;
use RiaanZA\LaravelSubscription\Tests\TestCase;

class UserSubscriptionTest extends TestCase
{
    /** @test */
    public function it_can_be_created_with_valid_attributes()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();

        $subscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->assertModelHasAttributes($subscription, [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertEquals($user->id, $subscription->user->id);
    }

    /** @test */
    public function it_belongs_to_plan()
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription(plan: $plan);

        $this->assertEquals($plan->id, $subscription->plan->id);
    }

    /** @test */
    public function it_has_usage_records()
    {
        $subscription = $this->createSubscription();
        $usage = SubscriptionUsage::factory()->create(['subscription_id' => $subscription->id]);

        $this->assertTrue($subscription->usage()->exists());
        $this->assertEquals($usage->id, $subscription->usage->first()->id);
    }

    /** @test */
    public function it_can_check_if_active()
    {
        $activeSubscription = $this->createSubscription(attributes: ['status' => 'active']);
        $cancelledSubscription = $this->createSubscription(attributes: ['status' => 'cancelled']);

        $this->assertTrue($activeSubscription->isActive());
        $this->assertFalse($cancelledSubscription->isActive());
    }

    /** @test */
    public function it_can_check_if_on_trial()
    {
        $trialSubscription = $this->createSubscription(attributes: [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(7),
        ]);
        $activeSubscription = $this->createSubscription(attributes: ['status' => 'active']);

        $this->assertTrue($trialSubscription->onTrial());
        $this->assertFalse($activeSubscription->onTrial());
    }

    /** @test */
    public function it_can_check_if_cancelled()
    {
        $cancelledSubscription = $this->createSubscription(attributes: [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
        $activeSubscription = $this->createSubscription(attributes: ['status' => 'active']);

        $this->assertTrue($cancelledSubscription->isCancelled());
        $this->assertFalse($activeSubscription->isCancelled());
    }

    /** @test */
    public function it_can_check_if_expired()
    {
        $expiredSubscription = $this->createSubscription(attributes: [
            'status' => 'expired',
            'ends_at' => now()->subDay(),
        ]);
        $activeSubscription = $this->createSubscription(attributes: ['status' => 'active']);

        $this->assertTrue($expiredSubscription->isExpired());
        $this->assertFalse($activeSubscription->isExpired());
    }

    /** @test */
    public function it_can_check_if_past_due()
    {
        $pastDueSubscription = $this->createSubscription(attributes: ['status' => 'past_due']);
        $activeSubscription = $this->createSubscription(attributes: ['status' => 'active']);

        $this->assertTrue($pastDueSubscription->isPastDue());
        $this->assertFalse($activeSubscription->isPastDue());
    }

    /** @test */
    public function it_can_calculate_trial_days_remaining()
    {
        $subscription = $this->createSubscription(attributes: [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(5),
        ]);

        $this->assertEquals(5, $subscription->trial_days_remaining);
    }

    /** @test */
    public function it_returns_zero_trial_days_for_non_trial_subscription()
    {
        $subscription = $this->createSubscription(attributes: ['status' => 'active']);

        $this->assertEquals(0, $subscription->trial_days_remaining);
    }

    /** @test */
    public function it_can_calculate_days_remaining()
    {
        $subscription = $this->createSubscription(attributes: [
            'ends_at' => now()->addDays(10),
        ]);

        $this->assertEquals(10, $subscription->days_remaining);
    }

    /** @test */
    public function it_can_format_amount()
    {
        $plan = SubscriptionPlan::factory()->create(['price' => 29.99]);
        $subscription = $this->createSubscription(plan: $plan);

        $this->assertEquals('$29.99', $subscription->formatted_amount);
    }

    /** @test */
    public function it_can_cancel_subscription()
    {
        $subscription = $this->createSubscription(attributes: ['status' => 'active']);

        $subscription->cancel();

        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
    }

    /** @test */
    public function it_can_resume_subscription()
    {
        $subscription = $this->createSubscription(attributes: [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $subscription->resume();

        $this->assertEquals('active', $subscription->status);
        $this->assertNull($subscription->cancelled_at);
    }

    /** @test */
    public function it_can_expire_subscription()
    {
        $subscription = $this->createSubscription(attributes: ['status' => 'active']);

        $subscription->expire();

        $this->assertEquals('expired', $subscription->status);
        $this->assertNotNull($subscription->ends_at);
    }

    /** @test */
    public function it_can_check_if_subscription_has_feature()
    {
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_access',
            'feature_name' => 'API Access',
            'feature_type' => 'boolean',
            'feature_limit' => '1',
        ]);

        $subscription = $this->createSubscription(plan: $plan);

        $this->assertTrue($subscription->hasFeature('api_access'));
        $this->assertFalse($subscription->hasFeature('non_existent_feature'));
    }

    /** @test */
    public function it_can_get_current_usage_for_feature()
    {
        $subscription = $this->createSubscription();
        
        SubscriptionUsage::factory()->create([
            'subscription_id' => $subscription->id,
            'feature_key' => 'api_calls',
            'usage_count' => 50,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);

        $usage = $subscription->getCurrentUsage('api_calls');

        $this->assertEquals(50, $usage);
    }

    /** @test */
    public function it_returns_zero_usage_for_non_existent_feature()
    {
        $subscription = $this->createSubscription();

        $usage = $subscription->getCurrentUsage('non_existent_feature');

        $this->assertEquals(0, $usage);
    }

    /** @test */
    public function it_can_check_if_ending_soon()
    {
        $endingSoon = $this->createSubscription(attributes: [
            'ends_at' => now()->addDays(2),
        ]);
        $notEndingSoon = $this->createSubscription(attributes: [
            'ends_at' => now()->addDays(10),
        ]);

        $this->assertTrue($endingSoon->isEndingSoon());
        $this->assertFalse($notEndingSoon->isEndingSoon());
    }

    /** @test */
    public function it_can_check_if_ending_soon_with_custom_days()
    {
        $subscription = $this->createSubscription(attributes: [
            'ends_at' => now()->addDays(8),
        ]);

        $this->assertTrue($subscription->isEndingSoon(10));
        $this->assertFalse($subscription->isEndingSoon(5));
    }

    /** @test */
    public function it_can_renew_subscription()
    {
        $subscription = $this->createSubscription(attributes: [
            'ends_at' => now()->addDays(5),
        ]);

        $originalEndsAt = $subscription->ends_at;
        $subscription->renew();

        $this->assertTrue($subscription->ends_at->gt($originalEndsAt));
    }

    /** @test */
    public function it_can_change_plan()
    {
        $oldPlan = SubscriptionPlan::factory()->create(['price' => 10.00]);
        $newPlan = SubscriptionPlan::factory()->create(['price' => 20.00]);
        $subscription = $this->createSubscription(plan: $oldPlan);

        $subscription->changePlan($newPlan);

        $this->assertEquals($newPlan->id, $subscription->plan_id);
    }

    /** @test */
    public function it_can_scope_active_subscriptions()
    {
        $this->createSubscription(attributes: ['status' => 'active']);
        $this->createSubscription(attributes: ['status' => 'cancelled']);

        $activeSubscriptions = UserSubscription::active()->get();

        $this->assertCount(1, $activeSubscriptions);
        $this->assertEquals('active', $activeSubscriptions->first()->status);
    }

    /** @test */
    public function it_can_scope_trial_subscriptions()
    {
        $this->createSubscription(attributes: ['status' => 'trial']);
        $this->createSubscription(attributes: ['status' => 'active']);

        $trialSubscriptions = UserSubscription::onTrial()->get();

        $this->assertCount(1, $trialSubscriptions);
        $this->assertEquals('trial', $trialSubscriptions->first()->status);
    }

    /** @test */
    public function it_can_scope_cancelled_subscriptions()
    {
        $this->createSubscription(attributes: ['status' => 'cancelled']);
        $this->createSubscription(attributes: ['status' => 'active']);

        $cancelledSubscriptions = UserSubscription::cancelled()->get();

        $this->assertCount(1, $cancelledSubscriptions);
        $this->assertEquals('cancelled', $cancelledSubscriptions->first()->status);
    }

    /** @test */
    public function it_can_scope_ending_soon_subscriptions()
    {
        $this->createSubscription(attributes: ['ends_at' => now()->addDays(2)]);
        $this->createSubscription(attributes: ['ends_at' => now()->addDays(10)]);

        $endingSoonSubscriptions = UserSubscription::endingSoon()->get();

        $this->assertCount(1, $endingSoonSubscriptions);
    }
}
