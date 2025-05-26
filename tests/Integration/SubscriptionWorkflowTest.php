<?php

namespace RiaanZA\LaravelSubscription\Tests\Integration;

use Carbon\Carbon;
use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Models\SubscriptionUsage;
use RiaanZA\LaravelSubscription\Services\SubscriptionService;
use RiaanZA\LaravelSubscription\Services\UsageService;
use RiaanZA\LaravelSubscription\Tests\TestCase;

class SubscriptionWorkflowTest extends TestCase
{
    private SubscriptionService $subscriptionService;
    private UsageService $usageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionService = app(SubscriptionService::class);
        $this->usageService = app(UsageService::class);
    }

    /** @test */
    public function complete_subscription_lifecycle_workflow()
    {
        // 1. Create user and plan
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Professional',
            'price' => 29.99,
            'billing_cycle' => 'monthly',
            'trial_days' => 14,
        ]);

        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);

        // 2. Start trial subscription
        $subscription = $this->subscriptionService->createSubscription($user, $plan, [], true);
        
        $this->assertEquals('trial', $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);

        // 3. Use features during trial
        $this->usageService->incrementUsage($user, 'api_calls', 100);
        $currentUsage = $this->usageService->getCurrentUsage($user, 'api_calls');
        $this->assertEquals(100, $currentUsage);

        // 4. Convert trial to paid subscription
        $paidSubscription = $this->subscriptionService->convertTrialToPaid($subscription);
        
        $this->assertEquals('active', $paidSubscription->status);
        $this->assertNull($paidSubscription->trial_ends_at);

        // 5. Continue using features
        $this->usageService->incrementUsage($user, 'api_calls', 200);
        $currentUsage = $this->usageService->getCurrentUsage($user, 'api_calls');
        $this->assertEquals(300, $currentUsage);

        // 6. Upgrade to higher plan
        $premiumPlan = SubscriptionPlan::factory()->create([
            'name' => 'Premium',
            'price' => 59.99,
            'billing_cycle' => 'monthly',
        ]);

        $premiumPlan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '5000',
        ]);

        $upgradedSubscription = $this->subscriptionService->changePlan($paidSubscription, $premiumPlan);
        
        $this->assertEquals($premiumPlan->id, $upgradedSubscription->plan_id);
        $this->assertEquals('active', $upgradedSubscription->status);

        // 7. Usage should persist after plan change
        $currentUsage = $this->usageService->getCurrentUsage($user, 'api_calls');
        $this->assertEquals(300, $currentUsage);

        // 8. Cancel subscription
        $cancelledSubscription = $this->subscriptionService->cancelSubscription($upgradedSubscription);
        
        $this->assertEquals('cancelled', $cancelledSubscription->status);
        $this->assertNotNull($cancelledSubscription->cancelled_at);

        // 9. Resume subscription before it expires
        $resumedSubscription = $this->subscriptionService->resumeSubscription($cancelledSubscription);
        
        $this->assertEquals('active', $resumedSubscription->status);
        $this->assertNull($resumedSubscription->cancelled_at);

        // 10. Final cancellation and expiration
        $finalCancellation = $this->subscriptionService->cancelSubscription($resumedSubscription, true);
        
        $this->assertEquals('cancelled', $finalCancellation->status);
        $this->assertTrue($finalCancellation->ends_at->isPast());
    }

    /** @test */
    public function usage_limits_and_alerts_workflow()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '100',
        ]);

        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        // 1. Normal usage
        $this->usageService->incrementUsage($user, 'api_calls', 50);
        $this->assertTrue($this->usageService->canUseFeature($user, 'api_calls', 30));

        // 2. Approaching limit (80% usage)
        $this->usageService->incrementUsage($user, 'api_calls', 30);
        $nearLimitFeatures = $this->usageService->getFeaturesNearLimit($user);
        $this->assertCount(1, $nearLimitFeatures);

        // 3. Check usage alerts
        $alerts = $this->usageService->getUsageAlerts($user);
        $this->assertArrayHasKey('near_limit_features', $alerts);
        $this->assertCount(1, $alerts['near_limit_features']);

        // 4. Exceed limit
        $this->expectException(\RiaanZA\LaravelSubscription\Exceptions\UsageLimitExceededException::class);
        $this->usageService->incrementUsage($user, 'api_calls', 25);
    }

    /** @test */
    public function subscription_renewal_workflow()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create([
            'billing_cycle' => 'monthly',
            'price' => 19.99,
        ]);

        $subscription = $this->subscriptionService->createSubscription($user, $plan);
        $originalEndsAt = $subscription->ends_at;

        // Travel to renewal date
        $this->travel($subscription->ends_at);

        // Process renewal
        $renewedSubscription = $this->subscriptionService->processRenewal($subscription);

        $this->assertEquals('active', $renewedSubscription->status);
        $this->assertTrue($renewedSubscription->ends_at->gt($originalEndsAt));
        $this->assertEquals(30, $renewedSubscription->ends_at->diffInDays($originalEndsAt));
    }

    /** @test */
    public function failed_payment_and_grace_period_workflow()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        // 1. Simulate failed payment
        $pastDueSubscription = $this->subscriptionService->handleFailedPayment($subscription);
        
        $this->assertEquals('past_due', $pastDueSubscription->status);

        // 2. Travel through grace period
        $gracePeriodDays = config('laravel-subscription.grace_period_days', 3);
        $this->travel($gracePeriodDays)->days();

        // 3. Expire subscription after grace period
        $expiredSubscription = $this->subscriptionService->expireSubscription($pastDueSubscription);
        
        $this->assertEquals('expired', $expiredSubscription->status);
    }

    /** @test */
    public function plan_feature_inheritance_workflow()
    {
        $user = $this->createUser();
        
        // Create basic plan
        $basicPlan = SubscriptionPlan::factory()->create(['name' => 'Basic']);
        $basicPlan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);

        // Create premium plan with more features
        $premiumPlan = SubscriptionPlan::factory()->create(['name' => 'Premium']);
        $premiumPlan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '10000',
        ]);
        $premiumPlan->features()->create([
            'feature_key' => 'advanced_analytics',
            'feature_name' => 'Advanced Analytics',
            'feature_type' => 'boolean',
            'feature_limit' => '1',
        ]);

        // Start with basic plan
        $subscription = $this->subscriptionService->createSubscription($user, $basicPlan);
        
        $this->assertTrue($subscription->hasFeature('api_calls'));
        $this->assertFalse($subscription->hasFeature('advanced_analytics'));

        // Upgrade to premium
        $upgradedSubscription = $this->subscriptionService->changePlan($subscription, $premiumPlan);
        
        $this->assertTrue($upgradedSubscription->hasFeature('api_calls'));
        $this->assertTrue($upgradedSubscription->hasFeature('advanced_analytics'));

        // Check feature limits
        $apiFeature = $upgradedSubscription->plan->getFeature('api_calls');
        $this->assertEquals('10000', $apiFeature->feature_limit);
    }

    /** @test */
    public function subscription_pause_and_resume_workflow()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        // Use some features
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);
        $this->usageService->incrementUsage($user, 'api_calls', 100);

        // Pause subscription
        $pausedSubscription = $this->subscriptionService->pauseSubscription($subscription);
        
        $this->assertEquals('paused', $pausedSubscription->status);
        $this->assertNotNull($pausedSubscription->paused_at);

        // Try to use features while paused (should fail)
        $this->assertFalse($this->usageService->canUseFeature($user, 'api_calls', 1));

        // Resume subscription
        $resumedSubscription = $this->subscriptionService->unpauseSubscription($pausedSubscription);
        
        $this->assertEquals('active', $resumedSubscription->status);
        $this->assertNull($resumedSubscription->paused_at);

        // Features should work again
        $this->assertTrue($this->usageService->canUseFeature($user, 'api_calls', 1));

        // Usage should be preserved
        $currentUsage = $this->usageService->getCurrentUsage($user, 'api_calls');
        $this->assertEquals(100, $currentUsage);
    }

    /** @test */
    public function usage_reset_on_billing_cycle_workflow()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create(['billing_cycle' => 'monthly']);
        
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);

        $subscription = $this->subscriptionService->createSubscription($user, $plan);

        // Use features in current period
        $this->usageService->incrementUsage($user, 'api_calls', 500);
        $this->assertEquals(500, $this->usageService->getCurrentUsage($user, 'api_calls'));

        // Travel to next billing period
        $this->travel(30)->days();

        // Process renewal (this should reset usage)
        $renewedSubscription = $this->subscriptionService->processRenewal($subscription);

        // Usage should be reset for new period
        $this->assertEquals(0, $this->usageService->getCurrentUsage($user, 'api_calls'));

        // Should be able to use full limit again
        $this->assertTrue($this->usageService->canUseFeature($user, 'api_calls', 1000));
    }
}
