<?php

namespace RiaanZA\LaravelSubscription;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use RiaanZA\LaravelSubscription\Console\Commands\InstallCommand;
use RiaanZA\LaravelSubscription\Console\Commands\InstallAuthCommand;
use RiaanZA\LaravelSubscription\Console\Commands\SeedPlansCommand;
use RiaanZA\LaravelSubscription\Console\Commands\ListPlansCommand;
use RiaanZA\LaravelSubscription\Console\Commands\CleanupCommand;
use RiaanZA\LaravelSubscription\Console\Commands\StatsCommand;
use RiaanZA\LaravelSubscription\Http\Middleware\SubscriptionMiddleware;
use Illuminate\Support\Facades\Gate;

class LaravelSubscriptionServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-subscription.php',
            'laravel-subscription'
        );

        // Register services
        $this->app->singleton('laravel-subscription', function ($app) {
            return new LaravelSubscriptionManager($app);
        });

        // Bind services
        $this->app->bind(\RiaanZA\LaravelSubscription\Services\SubscriptionService::class);
        $this->app->bind(\RiaanZA\LaravelSubscription\Services\UsageService::class);
        $this->app->bind(\RiaanZA\LaravelSubscription\Services\FeatureService::class);
        $this->app->bind(\RiaanZA\LaravelSubscription\Services\PeachPaymentsService::class);

        // Bind PeachPayments class if available
        $this->app->bind('RiaanZA\PeachPayments\PeachPayments', function ($app) {
            if (class_exists('\RiaanZA\PeachPayments\PeachPayments')) {
                return $app->make('\RiaanZA\PeachPayments\PeachPayments');
            }

            // Return a mock implementation if the class doesn't exist
            return new class {
                public function customers() {
                    throw new \Exception('PeachPayments package not installed. Please install peachpayments/laravel-subscriptions.');
                }

                public function subscriptions() {
                    throw new \Exception('PeachPayments package not installed. Please install peachpayments/laravel-subscriptions.');
                }

                public function plans() {
                    throw new \Exception('PeachPayments package not installed. Please install peachpayments/laravel-subscriptions.');
                }

                public function paymentMethods() {
                    throw new \Exception('PeachPayments package not installed. Please install peachpayments/laravel-subscriptions.');
                }
            };
        });

        // Configure service dependencies
        $this->app->afterResolving(\RiaanZA\LaravelSubscription\Services\SubscriptionService::class, function ($subscriptionService, $app) {
            $subscriptionService->setUsageService($app->make(\RiaanZA\LaravelSubscription\Services\UsageService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/laravel-subscription.php' => config_path('laravel-subscription.php'),
        ], 'laravel-subscription-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'laravel-subscription-migrations');

        // Publish assets
        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js/vendor/laravel-subscription'),
            __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-subscription'),
        ], 'laravel-subscription-assets');

        // Publish stubs
        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/laravel-subscription'),
        ], 'laravel-subscription-stubs');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/auth.php');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-subscription');

        // Register middleware
        $this->app['router']->aliasMiddleware('subscription', SubscriptionMiddleware::class);
        $this->app['router']->aliasMiddleware('usage-limit', \RiaanZA\LaravelSubscription\Http\Middleware\UsageLimitMiddleware::class);

        // Register policies
        $this->registerPolicies();

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                InstallAuthCommand::class,
                SeedPlansCommand::class,
                ListPlansCommand::class,
                CleanupCommand::class,
                StatsCommand::class,
            ]);
        }
    }

    /**
     * Register authorization policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(
            \RiaanZA\LaravelSubscription\Models\UserSubscription::class,
            \RiaanZA\LaravelSubscription\Policies\SubscriptionPolicy::class
        );

        Gate::policy(
            \RiaanZA\LaravelSubscription\Models\SubscriptionPlan::class,
            \RiaanZA\LaravelSubscription\Policies\PlanPolicy::class
        );

        Gate::policy(
            \RiaanZA\LaravelSubscription\Models\PlanFeature::class,
            \RiaanZA\LaravelSubscription\Policies\FeaturePolicy::class
        );

        Gate::policy(
            \RiaanZA\LaravelSubscription\Models\SubscriptionUsage::class,
            \RiaanZA\LaravelSubscription\Policies\UsagePolicy::class
        );

        // Register feature-based gates
        $this->registerFeatureGates();
    }

    /**
     * Register feature-based authorization gates.
     */
    protected function registerFeatureGates(): void
    {
        // Dynamic feature gates based on configuration
        $featureGates = config('laravel-subscription.authorization.feature_gates', []);

        foreach ($featureGates as $feature => $allowedPlans) {
            Gate::define("access-{$feature}", function ($user) use ($feature) {
                if (!$user->hasActiveSubscription()) {
                    return false;
                }

                $subscription = $user->activeSubscription();
                $featureGates = config('laravel-subscription.authorization.feature_gates', []);

                if (!isset($featureGates[$feature])) {
                    return false;
                }

                return in_array($subscription->plan->slug, $featureGates[$feature]);
            });
        }

        // Common subscription gates
        Gate::define('has-active-subscription', function ($user) {
            return $user->hasActiveSubscription();
        });

        Gate::define('on-trial', function ($user) {
            return $user->onSubscriptionTrial();
        });

        Gate::define('subscription-ending-soon', function ($user) {
            return $user->isSubscriptionEndingSoon();
        });

        Gate::define('has-premium-plan', function ($user) {
            if (!$user->hasActiveSubscription()) {
                return false;
            }

            $subscription = $user->activeSubscription();
            $premiumPlans = config('laravel-subscription.authorization.premium_plans', []);

            return in_array($subscription->plan->slug, $premiumPlans);
        });

        Gate::define('has-enterprise-plan', function ($user) {
            if (!$user->hasActiveSubscription()) {
                return false;
            }

            $subscription = $user->activeSubscription();
            $enterprisePlans = config('laravel-subscription.authorization.enterprise_plans', []);

            return in_array($subscription->plan->slug, $enterprisePlans);
        });

        Gate::define('can-use-feature', function ($user, $featureKey, $increment = 1) {
            return $user->canUseFeature($featureKey, $increment);
        });

        Gate::define('is-subscription-admin', function ($user) {
            $adminEmails = config('laravel-subscription.authorization.admin_emails', []);

            if (method_exists($user, 'hasRole')) {
                return $user->hasRole('admin') || $user->hasRole('super-admin');
            }

            if (property_exists($user, 'is_admin')) {
                return $user->is_admin;
            }

            return in_array($user->email, $adminEmails);
        });
    }
}
