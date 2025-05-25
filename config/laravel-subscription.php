<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the models used by the subscription package.
    |
    */
    'models' => [
        'user' => App\Models\User::class,
        'subscription_plan' => RiaanZA\LaravelSubscription\Models\SubscriptionPlan::class,
        'plan_feature' => RiaanZA\LaravelSubscription\Models\PlanFeature::class,
        'user_subscription' => RiaanZA\LaravelSubscription\Models\UserSubscription::class,
        'subscription_usage' => RiaanZA\LaravelSubscription\Models\SubscriptionUsage::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Configure the table names used by the subscription package.
    |
    */
    'table_names' => [
        'subscription_plans' => 'subscription_plans',
        'plan_features' => 'plan_features',
        'user_subscriptions' => 'user_subscriptions',
        'subscription_usage' => 'subscription_usage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Peach Payments Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Peach Payments integration.
    |
    */
    'peach_payments' => [
        'webhook_url' => env('PEACH_WEBHOOK_URL', '/api/webhooks/peach-payments'),
        'return_url' => env('PEACH_RETURN_URL', '/subscription/success'),
        'cancel_url' => env('PEACH_CANCEL_URL', '/subscription/cancelled'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Configuration
    |--------------------------------------------------------------------------
    |
    | Configure subscription features and behavior.
    |
    */
    'features' => [
        'trial_enabled' => true,
        'proration_enabled' => true,
        'usage_tracking' => true,
        'grace_period_days' => 3,
        'auto_cancel_failed_payments' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the user interface settings.
    |
    */
    'ui' => [
        'theme' => 'default',
        'currency_symbol' => 'R',
        'currency_code' => 'ZAR',
        'date_format' => 'Y-m-d',
        'datetime_format' => 'Y-m-d H:i:s',
        'show_trial_banner' => true,
        'show_usage_warnings' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure routing for the subscription package.
    |
    */
    'routes' => [
        'prefix' => 'subscription',
        'middleware' => ['web', 'auth'],
        'api_prefix' => 'api/subscription',
        'api_middleware' => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure notifications for subscription events.
    |
    */
    'notifications' => [
        'trial_ending' => [
            'enabled' => true,
            'days_before' => 3,
        ],
        'subscription_ending' => [
            'enabled' => true,
            'days_before' => 7,
        ],
        'payment_failed' => [
            'enabled' => true,
            'retry_attempts' => 3,
        ],
        'usage_limit_warning' => [
            'enabled' => true,
            'threshold_percentage' => 80,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for subscription data.
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'prefix' => 'laravel_subscription',
    ],
];
