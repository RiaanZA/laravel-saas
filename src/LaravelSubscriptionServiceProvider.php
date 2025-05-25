<?php

namespace RiaanZA\LaravelSubscription;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use RiaanZA\LaravelSubscription\Console\Commands\InstallCommand;
use RiaanZA\LaravelSubscription\Console\Commands\SeedPlansCommand;
use RiaanZA\LaravelSubscription\Http\Middleware\SubscriptionMiddleware;

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

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-subscription');

        // Register middleware
        $this->app['router']->aliasMiddleware('subscription', SubscriptionMiddleware::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                SeedPlansCommand::class,
            ]);
        }
    }
}
