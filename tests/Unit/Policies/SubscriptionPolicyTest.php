<?php

namespace RiaanZA\LaravelSubscription\Tests\Unit\Policies;

use RiaanZA\LaravelSubscription\Models\SubscriptionPlan;
use RiaanZA\LaravelSubscription\Models\UserSubscription;
use RiaanZA\LaravelSubscription\Policies\SubscriptionPolicy;
use RiaanZA\LaravelSubscription\Tests\TestCase;

class SubscriptionPolicyTest extends TestCase
{
    private SubscriptionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new SubscriptionPolicy();
    }

    /** @test */
    public function user_can_view_own_subscription()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertTrue($this->policy->view($user, $subscription));
    }

    /** @test */
    public function user_cannot_view_other_users_subscription()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $subscription = $this->createSubscription($user2);

        $this->assertFalse($this->policy->view($user1, $subscription));
    }

    /** @test */
    public function admin_can_view_any_subscription()
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertTrue($this->policy->view($admin, $subscription));
    }

    /** @test */
    public function user_can_create_subscription_when_no_active_subscription()
    {
        $user = $this->createUser();

        $this->assertTrue($this->policy->create($user));
    }

    /** @test */
    public function user_cannot_create_subscription_when_has_active_subscription()
    {
        $user = $this->createUser();
        $this->createSubscription($user);

        $this->assertFalse($this->policy->create($user));
    }

    /** @test */
    public function admin_can_always_create_subscriptions()
    {
        $admin = $this->createAdmin();
        $this->createSubscription($admin);

        $this->assertTrue($this->policy->create($admin));
    }

    /** @test */
    public function user_can_update_own_subscription()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertTrue($this->policy->update($user, $subscription));
    }

    /** @test */
    public function user_cannot_update_other_users_subscription()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $subscription = $this->createSubscription($user2);

        $this->assertFalse($this->policy->update($user1, $subscription));
    }

    /** @test */
    public function user_can_cancel_own_active_subscription()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user, attributes: ['status' => 'active']);

        $this->assertTrue($this->policy->cancel($user, $subscription));
    }

    /** @test */
    public function user_cannot_cancel_already_cancelled_subscription()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user, attributes: [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->assertFalse($this->policy->cancel($user, $subscription));
    }

    /** @test */
    public function user_can_resume_own_cancelled_subscription()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user, attributes: [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => now()->addDays(5),
        ]);

        $this->assertTrue($this->policy->resume($user, $subscription));
    }

    /** @test */
    public function user_cannot_resume_active_subscription()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user, attributes: ['status' => 'active']);

        $this->assertFalse($this->policy->resume($user, $subscription));
    }

    /** @test */
    public function user_cannot_resume_expired_subscription()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user, attributes: [
            'status' => 'cancelled',
            'cancelled_at' => now()->subDays(10),
            'ends_at' => now()->subDays(5),
        ]);

        $this->assertFalse($this->policy->resume($user, $subscription));
    }

    /** @test */
    public function user_can_delete_own_subscription()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertTrue($this->policy->delete($user, $subscription));
    }

    /** @test */
    public function user_cannot_delete_other_users_subscription()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $subscription = $this->createSubscription($user2);

        $this->assertFalse($this->policy->delete($user1, $subscription));
    }

    /** @test */
    public function admin_can_delete_any_subscription()
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertTrue($this->policy->delete($admin, $subscription));
    }

    /** @test */
    public function user_can_view_own_usage()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertTrue($this->policy->viewUsage($user, $subscription));
    }

    /** @test */
    public function user_cannot_view_other_users_usage()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $subscription = $this->createSubscription($user2);

        $this->assertFalse($this->policy->viewUsage($user1, $subscription));
    }

    /** @test */
    public function user_can_manage_own_payment_methods()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertTrue($this->policy->managePaymentMethods($user, $subscription));
    }

    /** @test */
    public function user_cannot_manage_other_users_payment_methods()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $subscription = $this->createSubscription($user2);

        $this->assertFalse($this->policy->managePaymentMethods($user1, $subscription));
    }

    /** @test */
    public function user_can_view_own_billing_history()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertTrue($this->policy->viewBilling($user, $subscription));
    }

    /** @test */
    public function admin_can_view_any_billing_history()
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertTrue($this->policy->viewBilling($admin, $subscription));
    }

    /** @test */
    public function user_can_export_own_data()
    {
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertTrue($this->policy->exportData($user, $subscription));
    }

    /** @test */
    public function user_cannot_export_other_users_data()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $subscription = $this->createSubscription($user2);

        $this->assertFalse($this->policy->exportData($user1, $subscription));
    }

    /** @test */
    public function policy_respects_configuration_settings()
    {
        config(['laravel-subscription.authorization.policies.allow_cancellation' => false]);

        $user = $this->createUser();
        $subscription = $this->createSubscription($user, attributes: ['status' => 'active']);

        $this->assertFalse($this->policy->cancel($user, $subscription));
    }

    /** @test */
    public function policy_allows_multiple_subscriptions_when_configured()
    {
        config(['laravel-subscription.authorization.policies.allow_multiple_subscriptions' => true]);

        $user = $this->createUser();
        $this->createSubscription($user);

        $this->assertTrue($this->policy->create($user));
    }

    /** @test */
    public function policy_prevents_plan_changes_when_configured()
    {
        config(['laravel-subscription.authorization.policies.allow_plan_changes' => false]);

        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->assertFalse($this->policy->update($user, $subscription));
    }

    /** @test */
    public function policy_prevents_resumption_when_configured()
    {
        config(['laravel-subscription.authorization.policies.allow_resumption' => false]);

        $user = $this->createUser();
        $subscription = $this->createSubscription($user, attributes: [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => now()->addDays(5),
        ]);

        $this->assertFalse($this->policy->resume($user, $subscription));
    }
}
