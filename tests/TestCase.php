<?php

namespace RiaanZA\LaravelSubscription\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use RiaanZA\LaravelSubscription\LaravelSubscriptionServiceProvider;
use RiaanZA\LaravelSubscription\Tests\Support\Models\User;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'RiaanZA\\LaravelSubscription\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->artisan('migrate', ['--database' => 'testbench'])->run();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelSubscriptionServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Set up authentication model
        config()->set('auth.providers.users.model', User::class);

        // Package configuration
        config()->set('laravel-subscription.user_model', User::class);
        config()->set('laravel-subscription.currency', 'USD');
        config()->set('laravel-subscription.authorization.admin_emails', ['admin@test.com']);
        config()->set('laravel-subscription.authorization.premium_plans', ['professional', 'premium']);
        config()->set('laravel-subscription.authorization.enterprise_plans', ['enterprise']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Support/database/migrations');
    }

    /**
     * Create a test user.
     */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Create an admin user.
     */
    protected function createAdmin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    /**
     * Assert that a model has specific attributes.
     */
    protected function assertModelHasAttributes($model, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->assertEquals($value, $model->getAttribute($key), "Model attribute '{$key}' does not match expected value.");
        }
    }

    /**
     * Assert that a response contains validation errors for specific fields.
     */
    protected function assertValidationErrors($response, array $fields): void
    {
        $response->assertStatus(422);

        foreach ($fields as $field) {
            $response->assertJsonValidationErrors($field);
        }
    }

    /**
     * Assert that a model exists in the database with specific attributes.
     */
    protected function assertDatabaseHasModel(string $model, array $attributes): void
    {
        $this->assertDatabaseHas((new $model)->getTable(), $attributes);
    }

    /**
     * Assert that a model does not exist in the database with specific attributes.
     */
    protected function assertDatabaseMissingModel(string $model, array $attributes): void
    {
        $this->assertDatabaseMissing((new $model)->getTable(), $attributes);
    }

    /**
     * Create a subscription plan for testing.
     */
    protected function createPlan(array $attributes = [])
    {
        return \RiaanZA\LaravelSubscription\Models\SubscriptionPlan::factory()->create($attributes);
    }

    /**
     * Create a subscription for testing.
     */
    protected function createSubscription(User $user = null, $plan = null, array $attributes = [])
    {
        $user = $user ?: $this->createUser();
        $plan = $plan ?: $this->createPlan();

        return \RiaanZA\LaravelSubscription\Models\UserSubscription::factory()->create(array_merge([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ], $attributes));
    }

    /**
     * Travel to a specific date for testing.
     */
    protected function travelTo($date): void
    {
        $this->travel($date);
    }

    /**
     * Mock external services for testing.
     */
    protected function mockPaymentService(): void
    {
        $this->mock(\RiaanZA\LaravelSubscription\Services\PeachPaymentsService::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntent')->andReturn(['id' => 'pi_test_123']);
            $mock->shouldReceive('confirmPayment')->andReturn(true);
            $mock->shouldReceive('refundPayment')->andReturn(true);
        });
    }
}
