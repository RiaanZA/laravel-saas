<?php

namespace RiaanZA\LaravelSubscription\Tests\Unit\Models;

use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\PlanFeature;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    /** @test */
    public function it_can_be_created_with_valid_attributes()
    {
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price' => 29.99,
            'billing_cycle' => 'monthly',
        ]);

        $this->assertModelHasAttributes($plan, [
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price' => 29.99,
            'billing_cycle' => 'monthly',
        ]);
    }

    /** @test */
    public function it_has_features_relationship()
    {
        $plan = SubscriptionPlan::factory()->create();
        $feature = PlanFeature::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue($plan->features()->exists());
        $this->assertEquals($feature->id, $plan->features->first()->id);
    }

    /** @test */
    public function it_has_subscriptions_relationship()
    {
        $plan = SubscriptionPlan::factory()->create();
        $user = $this->createUser();
        $subscription = UserSubscription::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($plan->subscriptions()->exists());
        $this->assertEquals($subscription->id, $plan->subscriptions->first()->id);
    }

    /** @test */
    public function it_formats_price_correctly()
    {
        $plan = SubscriptionPlan::factory()->create(['price' => 29.99]);

        $this->assertEquals('$29.99', $plan->formatted_price);
    }

    /** @test */
    public function it_formats_price_without_decimals_for_whole_numbers()
    {
        $plan = SubscriptionPlan::factory()->create(['price' => 30.00]);

        $this->assertEquals('$30', $plan->formatted_price);
    }

    /** @test */
    public function it_can_check_if_active()
    {
        $activePlan = SubscriptionPlan::factory()->create(['is_active' => true]);
        $inactivePlan = SubscriptionPlan::factory()->create(['is_active' => false]);

        $this->assertTrue($activePlan->isActive());
        $this->assertFalse($inactivePlan->isActive());
    }

    /** @test */
    public function it_can_scope_active_plans()
    {
        SubscriptionPlan::factory()->create(['is_active' => true]);
        SubscriptionPlan::factory()->create(['is_active' => false]);

        $activePlans = SubscriptionPlan::active()->get();

        $this->assertCount(1, $activePlans);
        $this->assertTrue($activePlans->first()->is_active);
    }

    /** @test */
    public function it_can_scope_popular_plans()
    {
        SubscriptionPlan::factory()->create(['is_popular' => true]);
        SubscriptionPlan::factory()->create(['is_popular' => false]);

        $popularPlans = SubscriptionPlan::popular()->get();

        $this->assertCount(1, $popularPlans);
        $this->assertTrue($popularPlans->first()->is_popular);
    }

    /** @test */
    public function it_orders_plans_by_sort_order()
    {
        $plan1 = SubscriptionPlan::factory()->create(['sort_order' => 2]);
        $plan2 = SubscriptionPlan::factory()->create(['sort_order' => 1]);
        $plan3 = SubscriptionPlan::factory()->create(['sort_order' => 3]);

        $orderedPlans = SubscriptionPlan::ordered()->get();

        $this->assertEquals($plan2->id, $orderedPlans->first()->id);
        $this->assertEquals($plan3->id, $orderedPlans->last()->id);
    }

    /** @test */
    public function it_can_find_by_slug()
    {
        $plan = SubscriptionPlan::factory()->create(['slug' => 'premium-plan']);

        $foundPlan = SubscriptionPlan::findBySlug('premium-plan');

        $this->assertEquals($plan->id, $foundPlan->id);
    }

    /** @test */
    public function it_returns_null_when_slug_not_found()
    {
        $foundPlan = SubscriptionPlan::findBySlug('non-existent-plan');

        $this->assertNull($foundPlan);
    }

    /** @test */
    public function it_can_check_if_plan_has_feature()
    {
        $plan = SubscriptionPlan::factory()->create();
        PlanFeature::factory()->create([
            'plan_id' => $plan->id,
            'feature_key' => 'api_access',
        ]);

        $this->assertTrue($plan->hasFeature('api_access'));
        $this->assertFalse($plan->hasFeature('non_existent_feature'));
    }

    /** @test */
    public function it_can_get_feature_by_key()
    {
        $plan = SubscriptionPlan::factory()->create();
        $feature = PlanFeature::factory()->create([
            'plan_id' => $plan->id,
            'feature_key' => 'api_access',
        ]);

        $foundFeature = $plan->getFeature('api_access');

        $this->assertEquals($feature->id, $foundFeature->id);
    }

    /** @test */
    public function it_returns_null_when_feature_not_found()
    {
        $plan = SubscriptionPlan::factory()->create();

        $foundFeature = $plan->getFeature('non_existent_feature');

        $this->assertNull($foundFeature);
    }

    /** @test */
    public function it_can_calculate_yearly_price()
    {
        $monthlyPlan = SubscriptionPlan::factory()->create([
            'price' => 10.00,
            'billing_cycle' => 'monthly',
        ]);

        $yearlyPrice = $monthlyPlan->getYearlyPrice();

        $this->assertEquals(120.00, $yearlyPrice);
    }

    /** @test */
    public function it_returns_same_price_for_yearly_plans()
    {
        $yearlyPlan = SubscriptionPlan::factory()->create([
            'price' => 100.00,
            'billing_cycle' => 'yearly',
        ]);

        $yearlyPrice = $yearlyPlan->getYearlyPrice();

        $this->assertEquals(100.00, $yearlyPrice);
    }

    /** @test */
    public function it_can_calculate_monthly_equivalent()
    {
        $yearlyPlan = SubscriptionPlan::factory()->create([
            'price' => 120.00,
            'billing_cycle' => 'yearly',
        ]);

        $monthlyEquivalent = $yearlyPlan->getMonthlyEquivalent();

        $this->assertEquals(10.00, $monthlyEquivalent);
    }

    /** @test */
    public function it_can_check_if_plan_is_free()
    {
        $freePlan = SubscriptionPlan::factory()->create(['price' => 0]);
        $paidPlan = SubscriptionPlan::factory()->create(['price' => 10.00]);

        $this->assertTrue($freePlan->isFree());
        $this->assertFalse($paidPlan->isFree());
    }

    /** @test */
    public function it_can_get_trial_period_in_days()
    {
        $plan = SubscriptionPlan::factory()->create(['trial_days' => 14]);

        $this->assertEquals(14, $plan->getTrialDays());
    }

    /** @test */
    public function it_can_check_if_plan_has_trial()
    {
        $planWithTrial = SubscriptionPlan::factory()->create(['trial_days' => 14]);
        $planWithoutTrial = SubscriptionPlan::factory()->create(['trial_days' => 0]);

        $this->assertTrue($planWithTrial->hasTrial());
        $this->assertFalse($planWithoutTrial->hasTrial());
    }

    /** @test */
    public function it_can_get_plan_metadata()
    {
        $metadata = ['custom_field' => 'custom_value'];
        $plan = SubscriptionPlan::factory()->create(['metadata' => $metadata]);

        $this->assertEquals($metadata, $plan->getMetadata());
    }

    /** @test */
    public function it_can_set_plan_metadata()
    {
        $plan = SubscriptionPlan::factory()->create();
        $metadata = ['new_field' => 'new_value'];

        $plan->setMetadata($metadata);

        $this->assertEquals($metadata, $plan->metadata);
    }

    /** @test */
    public function it_can_add_to_plan_metadata()
    {
        $plan = SubscriptionPlan::factory()->create(['metadata' => ['existing' => 'value']]);

        $plan->addMetadata('new_key', 'new_value');

        $expected = ['existing' => 'value', 'new_key' => 'new_value'];
        $this->assertEquals($expected, $plan->metadata);
    }
}
