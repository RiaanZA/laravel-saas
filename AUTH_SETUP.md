# Authentication Setup for Laravel Subscription Package

This package includes complete authentication scaffolding built with Inertia.js and Vue 3, designed to work seamlessly with Laravel Sanctum.

## Quick Setup

After installing the Laravel Subscription package, run the authentication installer:

```bash
php artisan subscription:install-auth
```

This command will:
- **Setup Inertia.js infrastructure**: Creates `app.blade.php` layout and Inertia middleware
- **Publish all frontend components**: Vue 3 pages and components for authentication and subscriptions
- **Configure frontend**: Creates/updates `app.js` with standard Inertia page resolution
- **Setup build tools**: Publishes/merges `package.json`, `vite.config.js`, `tailwind.config.js`, etc.
- **Install dependencies**: Automatically runs `npm install` and optionally builds assets
- **Update User model**: Adds subscription traits to your User model
- **Publish configuration**: Package configuration files

### Command Options

```bash
# Standard installation (includes NPM install)
php artisan subscription:install-auth

# Skip NPM installation (manual setup)
php artisan subscription:install-auth --skip-npm

# Force overwrite existing files
php artisan subscription:install-auth --force
```

## Automatic Dependency Management

The install command automatically handles all required dependencies:

### NPM Dependencies Installed
- **Vue 3**: Frontend framework
- **Inertia.js**: SPA-like experience with server-side routing
- **Vite**: Modern build tool
- **Tailwind CSS**: Utility-first CSS framework
- **Ziggy**: Laravel route helper for JavaScript
- **All required plugins**: Vue plugin for Vite, Tailwind plugins, etc.

### Smart Package.json Handling
- **New projects**: Creates complete `package.json` with all dependencies
- **Existing projects**: Merges dependencies into existing `package.json`
- **No conflicts**: Preserves existing scripts and configuration

### Build Process
- Automatically runs `npm install`
- Optionally builds assets with `npm run build`
- Handles errors gracefully with helpful messages

## Manual Setup

If you prefer to set up authentication manually, follow these steps:

### 1. Publish Authentication Assets

```bash
php artisan vendor:publish --tag=laravel-subscription-assets
```

### 2. Update Your User Model

Add the `HasSubscriptions` trait to your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use RiaanZA\LaravelSubscription\Traits\HasSubscriptions;

class User extends Authenticatable
{
    use HasSubscriptions;

    // ... rest of your User model
}
```

### 3. Configure Inertia.js

Update your `resources/js/app.js` to include authentication pages:

```javascript
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

// Laravel Subscription Auth Pages
const authPages = import.meta.glob('./vendor/laravel-subscription/Pages/Auth/*.vue');

createInertiaApp({
  title: (title) => `${title} - Laravel`,
  resolve: (name) => {
    // Check for auth pages first
    if (name.startsWith('Auth/')) {
      const authPagePath = `./vendor/laravel-subscription/Pages/${name}.vue`;
      if (authPages[authPagePath]) {
        return authPages[authPagePath]();
      }
    }

    // Default page resolution
    return resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
  },
  setup({ el, App, props, plugin }) {
    return createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
  progress: {
    color: '#4F46E5',
  },
})
```

### 4. Build Assets

```bash
npm install && npm run build
```

## Available Routes

The authentication system provides the following routes:

- `GET /login` - Login form
- `POST /login` - Process login
- `GET /register` - Registration form
- `POST /register` - Process registration
- `GET /forgot-password` - Forgot password form
- `POST /forgot-password` - Send reset link
- `GET /reset-password/{token}` - Password reset form
- `POST /reset-password` - Process password reset
- `GET /verify-email` - Email verification notice
- `POST /logout` - Logout

### Subscription Routes

- `GET /subscription/plans` - View available plans
- `GET /subscription/dashboard` - Subscription dashboard
- `GET /subscription/checkout/{plan}` - Checkout page for a plan
- `GET /subscription/success` - Payment success page

## Authentication Flow

1. **Registration**: Users can register at `/register`
2. **Login**: Users can login at `/login`
3. **Email Verification**: Optional email verification flow
4. **Password Reset**: Full password reset functionality
5. **Subscription Access**: After login, users are redirected to subscription plans

## File Structure

After running the install command, all frontend files are published to:

```
resources/
├── js/
│   ├── Pages/
│   │   ├── Auth/
│   │   │   ├── Login.vue
│   │   │   ├── Register.vue
│   │   │   ├── ForgotPassword.vue
│   │   │   ├── ResetPassword.vue
│   │   │   └── VerifyEmail.vue
│   │   └── Subscription/
│   │       ├── Dashboard.vue
│   │       ├── Plans.vue
│   │       ├── Checkout.vue
│   │       └── Success.vue
│   ├── Components/
│   │   ├── Auth/
│   │   │   └── AuthLayout.vue
│   │   ├── Subscription/
│   │   │   ├── CancelSubscriptionModal.vue
│   │   │   ├── PaymentForm.vue
│   │   │   ├── PlanCard.vue
│   │   │   ├── SubscriptionStatus.vue
│   │   │   └── UsageMetrics.vue
│   │   └── UI/
│   │       └── LoadingSpinner.vue
│   ├── components/
│   │   └── subscription/
│   │       ├── AlertsPanel.vue
│   │       ├── PlanSelector.vue
│   │       ├── SubscriptionDashboard.vue
│   │       ├── UsageCard.vue
│   │       └── ... (more components)
│   ├── app.js
│   └── subscription.js
├── css/
│   └── app.css
└── views/
    └── app.blade.php
```

**Note**: The package includes two component directories:
- `Components/` (capitalized) - Used by Inertia.js pages
- `components/` (lowercase) - Used by standalone subscription.js for non-Inertia integration

## Customization

### Styling

All pages and components use Tailwind CSS classes. You can customize the styling by:

1. Running the install command: `php artisan subscription:install-auth`
2. Modifying the Vue components in:
   - `resources/js/Pages/Auth/` - Authentication pages
   - `resources/js/Pages/Subscription/` - Subscription pages
   - `resources/js/Components/` - All reusable components

### Redirects

You can customize where users are redirected after authentication by modifying the controllers:

- After login: `route('subscription.dashboard')`
- After registration: `route('subscription.plans.index')`
- After email verification: `route('subscription.dashboard')`

### Validation Rules

Password validation rules can be customized in your `config/auth.php`:

```php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

## Integration with Sanctum

The package is configured to work with Laravel Sanctum out of the box. The API routes use the `auth:sanctum` middleware, while web routes use the standard `auth` middleware.

## Troubleshooting

### Route [login] not defined

This error occurs when the authentication routes are not loaded. Make sure you've run the installation command or manually published the routes.

### Vue components not found

Ensure you've built your assets after installation:

```bash
npm run build
```

### Unable to locate file in Vite manifest

If you see an error like "Unable to locate file in Vite manifest: resources/js/Pages/Auth/Login.vue", this means the authentication pages weren't properly published. Run:

```bash
php artisan subscription:install-auth --force
npm run build
```

### NPM installation failed

If NPM installation fails during the install command:

```bash
# Skip NPM and install manually
php artisan subscription:install-auth --skip-npm
npm install
npm run build
```

### Node.js/NPM not available

If you see "NPM is not available":

1. Install Node.js from [nodejs.org](https://nodejs.org/)
2. Verify installation: `node --version && npm --version`
3. Re-run the install command

### Styling issues

Make sure Tailwind CSS is properly configured in your project. The install command creates a proper `tailwind.config.js`, but if you have custom configuration, ensure it includes:

```javascript
// tailwind.config.js
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  // ...
}
```

## Security Considerations

- All authentication routes include CSRF protection
- Password reset tokens expire after 60 minutes by default
- Email verification is optional but recommended
- Rate limiting is applied to sensitive routes
- Sessions are regenerated on login for security

## Next Steps

After setting up the complete frontend:

1. Run migrations: `php artisan migrate`
2. Seed some plans: `php artisan subscription:seed-plans`
3. Visit `/login` to test authentication
4. Visit `/subscription/plans` to test the subscription system
5. Visit `/subscription/dashboard` to test the subscription dashboard
