# Laravel Subscription Management Package

A comprehensive Laravel package for SaaS subscription management with Vue 3, Inertia.js, and Peach Payments integration.

## Features

- 💳 Complete subscription management system
- 🎨 Modern Vue 3 + Inertia.js frontend
- 💰 Peach Payments integration
- 📊 Usage tracking and limits
- 🔄 Flexible billing cycles
- 🎯 Feature-based access control
- 📱 Responsive design with Tailwind CSS
- 🔒 Laravel Sanctum authentication
- 🧪 Comprehensive testing suite

## Requirements

- PHP 8.1 or higher
- Laravel 11.0 or higher
- Vue 3
- Inertia.js
- Tailwind CSS

## Installation

1. Install the package via Composer:

```bash
composer require riaan-za/laravel-subscription-management
```

2. Install the package:

```bash
php artisan subscription:install
```

3. Run migrations:

```bash
php artisan migrate
```

4. Seed sample plans:

```bash
php artisan subscription:seed-plans
```

## Configuration

Publish and configure the package:

```bash
php artisan vendor:publish --provider="RiaanZA\LaravelSubscription\LaravelSubscriptionServiceProvider" --tag="config"
```

Add your Peach Payments credentials to `.env`:

```env
PEACH_ENVIRONMENT=test
PEACH_CLIENT_ID=your_client_id
PEACH_CLIENT_SECRET=your_client_secret
PEACH_WEBHOOK_SECRET=your_webhook_secret
```

## Usage

### Basic Usage

```php
// Check if user has a feature
$user->hasFeature('advanced_analytics');

// Get feature limit
$user->getSubscriptionPlan()->getFeatureLimit('max_users');

// Track usage
$user->activeSubscription->incrementUsage('api_calls', 1);
```

### Frontend Components

The package includes Vue 3 components for:

- Plan selection and comparison
- Subscription dashboard
- Usage metrics
- Payment forms
- Billing history

## Database Schema

The package creates the following tables:

- `subscription_plans` - Available subscription plans
- `plan_features` - Features included in each plan
- `user_subscriptions` - User subscription records
- `subscription_usage` - Usage tracking data

## API Endpoints

### Web Routes
- `GET /subscription/plans` - View available plans
- `GET /subscription/dashboard` - Subscription dashboard
- `POST /subscription/subscribe` - Create subscription

### API Routes
- `GET /api/subscription/plans` - Get plans (JSON)
- `GET /api/subscription/current` - Current subscription
- `POST /api/subscription/usage/{feature}/increment` - Track usage

## Testing

Run the test suite:

```bash
php artisan test
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security issues, please email security@example.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Riaan ZA](https://github.com/RiaanZA)
- [All Contributors](../../contributors)