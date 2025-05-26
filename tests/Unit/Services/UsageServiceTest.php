<?php

namespace RiaanZA\LaravelSubscription\Tests\Unit\Services;

use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\PlanFeature;
use RiaanZA\LaravelSubscription\Models\SubscriptionUsage;
use RiaanZA\LaravelSubscription\Services\UsageService;
use RiaanZA\LaravelSubscription\Tests\TestCase;
use RiaanZA\LaravelSubscription\Exceptions\UsageLimitExceededException;

class UsageServiceTest extends TestCase
{
    private UsageService $usageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usageService = app(UsageService::class);
    }

    /** @test */
    public function it_can_increment_usage()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 10);

        $usage = SubscriptionUsage::where('subscription_id', $subscription->id)
            ->where('feature_key', 'api_calls')
            ->first();

        $this->assertEquals(10, $usage->usage_count);
    }

    /** @test */
    public function it_can_decrement_usage()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 20);
        $this->usageService->decrementUsage($user, 'api_calls', 5);

        $usage = SubscriptionUsage::where('subscription_id', $subscription->id)
            ->where('feature_key', 'api_calls')
            ->first();

        $this->assertEquals(15, $usage->usage_count);
    }

    /** @test */
    public function it_throws_exception_when_exceeding_usage_limit()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '100',
        ]);
        $this->createSubscription($user, $plan);

        $this->expectException(UsageLimitExceededException::class);

        $this->usageService->incrementUsage($user, 'api_calls', 150);
    }

    /** @test */
    public function it_allows_unlimited_usage_for_unlimited_features()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '-1', // Unlimited
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 10000);

        $usage = SubscriptionUsage::where('subscription_id', $subscription->id)
            ->where('feature_key', 'api_calls')
            ->first();

        $this->assertEquals(10000, $usage->usage_count);
    }

    /** @test */
    public function it_can_get_current_usage()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 50);

        $currentUsage = $this->usageService->getCurrentUsage($user, 'api_calls');

        $this->assertEquals(50, $currentUsage);
    }

    /** @test */
    public function it_returns_zero_for_non_existent_usage()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $this->createSubscription($user, $plan);

        $currentUsage = $this->usageService->getCurrentUsage($user, 'non_existent_feature');

        $this->assertEquals(0, $currentUsage);
    }

    /** @test */
    public function it_can_check_if_user_can_use_feature()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '100',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 50);

        $this->assertTrue($this->usageService->canUseFeature($user, 'api_calls', 30));
        $this->assertFalse($this->usageService->canUseFeature($user, 'api_calls', 60));
    }

    /** @test */
    public function it_can_get_usage_statistics()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 250);

        $stats = $this->usageService->getUsageStatistics($user);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('api_calls', $stats);
        $this->assertEquals(250, $stats['api_calls']['current_usage']);
        $this->assertEquals(1000, $stats['api_calls']['limit']);
        $this->assertEquals(25, $stats['api_calls']['percentage_used']);
    }

    /** @test */
    public function it_can_get_features_over_limit()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '100',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        // Force usage over limit
        SubscriptionUsage::create([
            'subscription_id' => $subscription->id,
            'feature_key' => 'api_calls',
            'usage_count' => 150,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);

        $overLimitFeatures = $this->usageService->getFeaturesOverLimit($user);

        $this->assertCount(1, $overLimitFeatures);
        $this->assertEquals('api_calls', $overLimitFeatures[0]['feature_key']);
    }

    /** @test */
    public function it_can_get_features_near_limit()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '100',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 85); // 85% usage

        $nearLimitFeatures = $this->usageService->getFeaturesNearLimit($user);

        $this->assertCount(1, $nearLimitFeatures);
        $this->assertEquals('api_calls', $nearLimitFeatures[0]['feature_key']);
    }

    /** @test */
    public function it_can_reset_usage_for_feature()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 500);
        $this->usageService->resetUsage($user, 'api_calls');

        $currentUsage = $this->usageService->getCurrentUsage($user, 'api_calls');

        $this->assertEquals(0, $currentUsage);
    }

    /** @test */
    public function it_can_reset_all_usage()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);
        $plan->features()->create([
            'feature_key' => 'storage',
            'feature_name' => 'Storage',
            'feature_type' => 'numeric',
            'feature_limit' => '5000',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 500);
        $this->usageService->incrementUsage($user, 'storage', 2000);
        $this->usageService->resetAllUsage($user);

        $this->assertEquals(0, $this->usageService->getCurrentUsage($user, 'api_calls'));
        $this->assertEquals(0, $this->usageService->getCurrentUsage($user, 'storage'));
    }

    /** @test */
    public function it_can_get_detailed_usage_data()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 300);

        $detailedUsage = $this->usageService->getDetailedUsageData($user);

        $this->assertIsArray($detailedUsage);
        $this->assertArrayHasKey('features', $detailedUsage);
        $this->assertCount(1, $detailedUsage['features']);
        
        $feature = $detailedUsage['features'][0];
        $this->assertEquals('api_calls', $feature['key']);
        $this->assertEquals(300, $feature['current_usage']);
        $this->assertEquals(1000, $feature['limit']);
        $this->assertEquals(30, $feature['percentage_used']);
    }

    /** @test */
    public function it_handles_boolean_features()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'advanced_analytics',
            'feature_name' => 'Advanced Analytics',
            'feature_type' => 'boolean',
            'feature_limit' => '1',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $canUse = $this->usageService->canUseFeature($user, 'advanced_analytics');

        $this->assertTrue($canUse);
    }

    /** @test */
    public function it_can_get_usage_alerts()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '100',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 85);

        $alerts = $this->usageService->getUsageAlerts($user);

        $this->assertIsArray($alerts);
        $this->assertArrayHasKey('near_limit_features', $alerts);
        $this->assertCount(1, $alerts['near_limit_features']);
    }

    /** @test */
    public function it_can_export_usage_data()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $plan->features()->create([
            'feature_key' => 'api_calls',
            'feature_name' => 'API Calls',
            'feature_type' => 'numeric',
            'feature_limit' => '1000',
        ]);
        $subscription = $this->createSubscription($user, $plan);

        $this->usageService->incrementUsage($user, 'api_calls', 500);

        $exportData = $this->usageService->exportUsageData($user);

        $this->assertIsArray($exportData);
        $this->assertArrayHasKey('user_id', $exportData);
        $this->assertArrayHasKey('subscription_id', $exportData);
        $this->assertArrayHasKey('usage_data', $exportData);
    }
}
