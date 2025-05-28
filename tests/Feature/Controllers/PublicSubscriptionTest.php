<?php

namespace RiaanZA\LaravelSubscription\Tests\Feature\Controllers;

use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PublicSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_subscription_for_new_user_via_public_endpoint()
    {
        $plan = SubscriptionPlan::factory()->create([
            'slug' => 'enterprise',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $customerData = [
            'plan_slug' => 'enterprise',
            'start_trial' => true,
            'customer' => [
                'first_name' => 'Riaan',
                'last_name' => 'Laubscher',
                'email' => 'loophole1@gmail.com',
            ]
        ];

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('subscription.store.public'), $customerData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'subscription' => [
                'id',
                'status',
                'plan_name',
            ],
            'user' => [
                'id',
                'email',
                'name',
            ],
        ]);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'loophole1@gmail.com',
        ]);

        // Verify subscription was created
        $this->assertDatabaseHas('user_subscriptions', [
            'status' => 'trial',
            'plan_id' => $plan->id,
        ]);
    }

    /** @test */
    public function it_prevents_duplicate_subscriptions_for_existing_users()
    {
        $plan = SubscriptionPlan::factory()->create([
            'slug' => 'enterprise',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $user = $this->createUser(['email' => 'loophole1@gmail.com']);
        $this->createSubscription($user, ['status' => 'active']);

        $customerData = [
            'plan_slug' => 'enterprise',
            'start_trial' => true,
            'customer' => [
                'first_name' => 'Riaan',
                'last_name' => 'Laubscher',
                'email' => 'loophole1@gmail.com',
            ]
        ];

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('subscription.store.public'), $customerData);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'User already has an active subscription',
            'error' => 'existing_subscription',
        ]);
    }

    /** @test */
    public function it_validates_required_customer_fields()
    {
        $plan = SubscriptionPlan::factory()->create([
            'slug' => 'enterprise',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $customerData = [
            'plan_slug' => 'enterprise',
            'start_trial' => true,
            'customer' => [
                // Missing required fields
            ]
        ];

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('subscription.store.public'), $customerData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'customer.first_name',
            'customer.last_name',
            'customer.email',
        ]);
    }

    /** @test */
    public function it_prevents_trial_for_plans_without_trial_period()
    {
        $plan = SubscriptionPlan::factory()->create([
            'slug' => 'enterprise',
            'trial_days' => 0, // No trial period
            'is_active' => true,
        ]);

        $customerData = [
            'plan_slug' => 'enterprise',
            'start_trial' => true,
            'customer' => [
                'first_name' => 'Riaan',
                'last_name' => 'Laubscher',
                'email' => 'loophole1@gmail.com',
            ]
        ];

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('subscription.store.public'), $customerData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_trial']);
    }
}
