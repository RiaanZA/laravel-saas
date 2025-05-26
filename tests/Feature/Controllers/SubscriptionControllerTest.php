<?php

namespace RiaanZA\LaravelSubscription\Tests\Feature\Controllers;

use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_display_subscription_dashboard()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription($user, $plan);

        $response = $this->actingAs($user)->get(route('subscription.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('subscription.dashboard');
        $response->assertViewHas('subscription');
    }

    /** @test */
    public function it_can_create_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();

        $response = $this->actingAs($user)->post(route('subscription.store'), [
            'plan_slug' => $plan->slug,
        ]);

        $response->assertRedirect(route('subscription.dashboard'));
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_can_create_trial_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create(['trial_days' => 14]);

        $response = $this->actingAs($user)->post(route('subscription.store'), [
            'plan_slug' => $plan->slug,
            'start_trial' => true,
        ]);

        $response->assertRedirect(route('subscription.dashboard'));
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_subscription()
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('subscription.store'), []);

        $this->assertValidationErrors($response, ['plan_slug']);
    }

    /** @test */
    public function it_prevents_creating_subscription_when_user_already_has_active_subscription()
    {
        $user = $this->createUser();
        $plan1 = SubscriptionPlan::factory()->create();
        $plan2 = SubscriptionPlan::factory()->create();
        $this->createSubscription($user, $plan1);

        $response = $this->actingAs($user)->post(route('subscription.store'), [
            'plan_slug' => $plan2->slug,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function it_can_update_subscription_plan()
    {
        $user = $this->createUser();
        $oldPlan = SubscriptionPlan::factory()->create();
        $newPlan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription($user, $oldPlan);

        $response = $this->actingAs($user)->put(route('subscription.update', $subscription), [
            'plan_slug' => $newPlan->slug,
        ]);

        $response->assertRedirect(route('subscription.dashboard'));
        $subscription->refresh();
        $this->assertEquals($newPlan->id, $subscription->plan_id);
    }

    /** @test */
    public function it_can_cancel_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription($user, $plan);

        $response = $this->actingAs($user)->post(route('subscription.cancel', $subscription), [
            'cancellation_reason' => 'No longer needed',
        ]);

        $response->assertRedirect(route('subscription.dashboard'));
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
    }

    /** @test */
    public function it_can_resume_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription($user, $plan, [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('subscription.resume', $subscription));

        $response->assertRedirect(route('subscription.dashboard'));
        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertNull($subscription->cancelled_at);
    }

    /** @test */
    public function it_prevents_unauthorized_access_to_other_users_subscriptions()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription($user2, $plan);

        $response = $this->actingAs($user1)->put(route('subscription.update', $subscription), [
            'plan_slug' => $plan->slug,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_json_response_for_api_requests()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('subscription.store'), [
                'plan_slug' => $plan->slug,
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Subscription created successfully',
        ]);
    }

    /** @test */
    public function it_can_get_subscription_data_via_api()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription($user, $plan);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/api/subscription/current');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'subscription' => [
                'id',
                'status',
                'plan',
            ],
        ]);
    }

    /** @test */
    public function it_can_get_available_plans()
    {
        SubscriptionPlan::factory()->count(3)->create(['is_active' => true]);
        SubscriptionPlan::factory()->create(['is_active' => false]);

        $response = $this->get('/api/subscription/public/plans');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'plans');
    }

    /** @test */
    public function it_requires_authentication_for_subscription_operations()
    {
        $plan = SubscriptionPlan::factory()->create();

        $response = $this->post(route('subscription.store'), [
            'plan_slug' => $plan->slug,
        ]);

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function it_can_handle_subscription_creation_errors()
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('subscription.store'), [
                'plan_slug' => 'non-existent-plan',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'error',
        ]);
    }

    /** @test */
    public function it_can_pause_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription($user, $plan);

        $response = $this->actingAs($user)->post(route('subscription.pause', $subscription));

        $response->assertRedirect(route('subscription.dashboard'));
        $subscription->refresh();
        $this->assertEquals('paused', $subscription->status);
    }

    /** @test */
    public function it_can_unpause_subscription()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription($user, $plan, [
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('subscription.unpause', $subscription));

        $response->assertRedirect(route('subscription.dashboard'));
        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
    }

    /** @test */
    public function it_can_get_subscription_usage_data()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription($user, $plan);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/api/subscription/usage/detailed');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'features',
        ]);
    }

    /** @test */
    public function it_can_increment_feature_usage()
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

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/subscription/usage/increment', [
                'feature_key' => 'api_calls',
                'increment' => 5,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Usage incremented successfully',
        ]);
    }

    /** @test */
    public function it_can_get_usage_alerts()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $this->createSubscription($user, $plan);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/api/subscription/usage/alerts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'over_limit_features',
            'near_limit_features',
        ]);
    }
}
