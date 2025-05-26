<?php

namespace RiaanZA\LaravelSubscription\Tests\Feature\Commands;

use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Tests\TestCase;

class SubscriptionCommandsTest extends TestCase
{
    /** @test */
    public function install_command_publishes_assets()
    {
        $this->artisan('subscription:install')
            ->expectsOutput('Laravel Subscription Management package installed successfully!')
            ->assertExitCode(0);

        // Check if config file was published
        $this->assertFileExists(config_path('laravel-subscription.php'));
    }

    /** @test */
    public function seed_plans_command_creates_sample_plans()
    {
        $this->artisan('subscription:seed-plans')
            ->expectsOutput('Sample subscription plans created successfully!')
            ->assertExitCode(0);

        // Check if plans were created
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'basic']);
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'professional']);
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'enterprise']);
    }

    /** @test */
    public function list_plans_command_displays_plans()
    {
        SubscriptionPlan::factory()->create(['name' => 'Test Plan', 'price' => 19.99]);

        $this->artisan('subscription:list-plans')
            ->expectsTable(
                ['ID', 'Name', 'Slug', 'Price', 'Billing Cycle', 'Status', 'Subscribers'],
                [
                    [1, 'Test Plan', 'test-plan', '$19.99', 'monthly', 'Active', 0]
                ]
            )
            ->assertExitCode(0);
    }

    /** @test */
    public function cleanup_command_removes_expired_subscriptions()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        
        // Create expired subscription
        $expiredSubscription = $this->createSubscription($user, $plan, [
            'status' => 'expired',
            'ends_at' => now()->subDays(30),
        ]);

        // Create active subscription
        $activeSubscription = $this->createSubscription($this->createUser(), $plan, [
            'status' => 'active',
            'ends_at' => now()->addDays(30),
        ]);

        $this->artisan('subscription:cleanup')
            ->expectsOutput('Cleaned up 1 expired subscription(s)')
            ->assertExitCode(0);

        // Expired subscription should be deleted
        $this->assertDatabaseMissing('user_subscriptions', ['id' => $expiredSubscription->id]);
        
        // Active subscription should remain
        $this->assertDatabaseHas('user_subscriptions', ['id' => $activeSubscription->id]);
    }

    /** @test */
    public function cleanup_command_with_dry_run_option()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        
        $expiredSubscription = $this->createSubscription($user, $plan, [
            'status' => 'expired',
            'ends_at' => now()->subDays(30),
        ]);

        $this->artisan('subscription:cleanup', ['--dry-run' => true])
            ->expectsOutput('Dry run: Would clean up 1 expired subscription(s)')
            ->assertExitCode(0);

        // Subscription should still exist
        $this->assertDatabaseHas('user_subscriptions', ['id' => $expiredSubscription->id]);
    }

    /** @test */
    public function stats_command_displays_subscription_statistics()
    {
        $plan1 = SubscriptionPlan::factory()->create(['name' => 'Basic']);
        $plan2 = SubscriptionPlan::factory()->create(['name' => 'Premium']);

        // Create various subscriptions
        $this->createSubscription($this->createUser(), $plan1, ['status' => 'active']);
        $this->createSubscription($this->createUser(), $plan1, ['status' => 'trial']);
        $this->createSubscription($this->createUser(), $plan2, ['status' => 'active']);
        $this->createSubscription($this->createUser(), $plan2, ['status' => 'cancelled']);

        $this->artisan('subscription:stats')
            ->expectsOutput('Subscription Statistics')
            ->expectsOutput('Total Subscriptions: 4')
            ->expectsOutput('Active Subscriptions: 2')
            ->expectsOutput('Trial Subscriptions: 1')
            ->expectsOutput('Cancelled Subscriptions: 1')
            ->assertExitCode(0);
    }

    /** @test */
    public function stats_command_with_plan_breakdown()
    {
        $plan = SubscriptionPlan::factory()->create(['name' => 'Test Plan']);
        
        $this->createSubscription($this->createUser(), $plan, ['status' => 'active']);
        $this->createSubscription($this->createUser(), $plan, ['status' => 'active']);

        $this->artisan('subscription:stats', ['--plans' => true])
            ->expectsOutput('Plan Breakdown:')
            ->expectsOutput('Test Plan: 2 subscribers')
            ->assertExitCode(0);
    }

    /** @test */
    public function stats_command_with_json_output()
    {
        $plan = SubscriptionPlan::factory()->create();
        $this->createSubscription($this->createUser(), $plan, ['status' => 'active']);

        $this->artisan('subscription:stats', ['--json' => true])
            ->expectsOutputToContain('"total_subscriptions":1')
            ->expectsOutputToContain('"active_subscriptions":1')
            ->assertExitCode(0);
    }

    /** @test */
    public function cleanup_command_respects_days_parameter()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        
        // Create subscription expired 5 days ago
        $expiredSubscription = $this->createSubscription($user, $plan, [
            'status' => 'expired',
            'ends_at' => now()->subDays(5),
        ]);

        // Cleanup with 10 days threshold (should clean up)
        $this->artisan('subscription:cleanup', ['--days' => 10])
            ->expectsOutput('Cleaned up 1 expired subscription(s)')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('user_subscriptions', ['id' => $expiredSubscription->id]);
    }

    /** @test */
    public function cleanup_command_respects_smaller_days_parameter()
    {
        $user = $this->createUser();
        $plan = SubscriptionPlan::factory()->create();
        
        // Create subscription expired 5 days ago
        $expiredSubscription = $this->createSubscription($user, $plan, [
            'status' => 'expired',
            'ends_at' => now()->subDays(5),
        ]);

        // Cleanup with 3 days threshold (should not clean up)
        $this->artisan('subscription:cleanup', ['--days' => 3])
            ->expectsOutput('Cleaned up 0 expired subscription(s)')
            ->assertExitCode(0);

        $this->assertDatabaseHas('user_subscriptions', ['id' => $expiredSubscription->id]);
    }

    /** @test */
    public function seed_plans_command_with_force_option_overwrites_existing()
    {
        // Create existing plan
        SubscriptionPlan::factory()->create(['slug' => 'basic', 'price' => 5.00]);

        $this->artisan('subscription:seed-plans', ['--force' => true])
            ->expectsOutput('Sample subscription plans created successfully!')
            ->assertExitCode(0);

        // Plan should be updated with new price
        $plan = SubscriptionPlan::where('slug', 'basic')->first();
        $this->assertNotEquals(5.00, $plan->price);
    }

    /** @test */
    public function list_plans_command_with_inactive_plans()
    {
        SubscriptionPlan::factory()->create(['name' => 'Active Plan', 'is_active' => true]);
        SubscriptionPlan::factory()->create(['name' => 'Inactive Plan', 'is_active' => false]);

        $this->artisan('subscription:list-plans', ['--all' => true])
            ->expectsOutputToContain('Active Plan')
            ->expectsOutputToContain('Inactive Plan')
            ->assertExitCode(0);
    }

    /** @test */
    public function list_plans_command_without_all_flag_shows_only_active()
    {
        SubscriptionPlan::factory()->create(['name' => 'Active Plan', 'is_active' => true]);
        SubscriptionPlan::factory()->create(['name' => 'Inactive Plan', 'is_active' => false]);

        $output = $this->artisan('subscription:list-plans')
            ->expectsOutputToContain('Active Plan')
            ->assertExitCode(0)
            ->getDisplay();

        $this->assertStringNotContainsString('Inactive Plan', $output);
    }

    /** @test */
    public function commands_handle_empty_database_gracefully()
    {
        $this->artisan('subscription:list-plans')
            ->expectsOutput('No subscription plans found.')
            ->assertExitCode(0);

        $this->artisan('subscription:stats')
            ->expectsOutput('Total Subscriptions: 0')
            ->assertExitCode(0);

        $this->artisan('subscription:cleanup')
            ->expectsOutput('Cleaned up 0 expired subscription(s)')
            ->assertExitCode(0);
    }
}
