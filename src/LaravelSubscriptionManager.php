<?php

namespace RiaanZA\LaravelSubscription;

use Illuminate\Foundation\Application;
use RiaanZA\LaravelSubscription\Services\SubscriptionService;
use RiaanZA\LaravelSubscription\Services\FeatureService;
use RiaanZA\LaravelSubscription\Services\PeachPaymentsService;

class LaravelSubscriptionManager
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get the subscription service instance.
     */
    public function subscriptions(): SubscriptionService
    {
        return $this->app->make(SubscriptionService::class);
    }

    /**
     * Get the feature service instance.
     */
    public function features(): FeatureService
    {
        return $this->app->make(FeatureService::class);
    }

    /**
     * Get the payment service instance.
     */
    public function payments(): PeachPaymentsService
    {
        return $this->app->make(PeachPaymentsService::class);
    }

    /**
     * Get package version.
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * Get package configuration.
     */
    public function config(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return config('laravel-subscription');
        }

        return config("laravel-subscription.{$key}", $default);
    }
}
