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

- PHP 8.2 or higher
- Laravel 12.0 or higher
- Vue 3
- Inertia.js
- Tailwind CSS

## Installation

1. Install the package via Composer:

```bash
composer require riaan-za/laravel-subscription-management
```

> **Note**: Version 2.x requires Laravel 12+ and PHP 8.2+. For Laravel 11 support, use version 1.x.

2. Install the package:

```bash
php artisan subscription:install
```

3. Install authentication scaffolding:

```bash
php artisan subscription:install-auth
```

4. Build frontend assets:

```bash
npm install && npm run build
```

5. Run migrations:

```bash
php artisan migrate
```

6. Seed sample plans:

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

## Authentication

The package includes complete authentication scaffolding built with Inertia.js and Vue 3:

- **Login/Register**: Full user authentication system
- **Password Reset**: Email-based password recovery
- **Email Verification**: Optional email verification flow
- **Laravel Sanctum**: API authentication ready

### Authentication Routes

- `/login` - User login
- `/register` - User registration
- `/forgot-password` - Password reset request
- `/reset-password/{token}` - Password reset form
- `/verify-email` - Email verification

### Customization

For detailed authentication setup and customization options, see [AUTH_SETUP.md](AUTH_SETUP.md).

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