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

    /*
    |--------------------------------------------------------------------------
    | Authorization Configuration
    |--------------------------------------------------------------------------
    |
    | Configure authorization settings for subscription management.
    |
    */
    'authorization' => [
        // Admin email addresses for policy checks
        'admin_emails' => array_filter(explode(',', env('SUBSCRIPTION_ADMIN_EMAILS', ''))),

        // Super admin email addresses
        'super_admin_emails' => array_filter(explode(',', env('SUBSCRIPTION_SUPER_ADMIN_EMAILS', ''))),

        // Premium plan slugs
        'premium_plans' => ['professional', 'premium', 'pro'],

        // Enterprise plan slugs
        'enterprise_plans' => ['enterprise', 'business'],

        // Price thresholds for plan tiers
        'premium_threshold' => 25.00,
        'enterprise_threshold' => 75.00,

        // Policy settings
        'policies' => [
            'allow_multiple_subscriptions' => false,
            'allow_plan_changes' => true,
            'allow_cancellation' => true,
            'allow_resumption' => true,
            'require_payment_method' => true,
            'allow_trial_extensions' => false,
            'allow_usage_overrides' => false,
        ],

        // Feature access control
        'feature_gates' => [
            'api_access' => ['professional', 'enterprise'],
            'advanced_analytics' => ['professional', 'enterprise'],
            'priority_support' => ['professional', 'enterprise'],
            'phone_support' => ['enterprise'],
            'white_label' => ['enterprise'],
            'sso_integration' => ['enterprise'],
            'custom_branding' => ['enterprise'],
            'dedicated_manager' => ['enterprise'],
        ],
    ],
];
