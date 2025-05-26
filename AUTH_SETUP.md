# Authentication Setup for Laravel Subscription Package

This package includes complete authentication scaffolding built with Inertia.js and Vue 3, designed to work seamlessly with Laravel Sanctum.

## Quick Setup

After installing the Laravel Subscription package, run the authentication installer:

```bash
php artisan subscription:install-auth
```

This command will:
- **Setup Inertia.js infrastructure**: Creates `app.blade.php` layout and Inertia middleware
- **Publish authentication components**: Vue 3 authentication pages and components to main directories
- **Configure frontend**: Creates/updates `app.js` with standard Inertia page resolution
- **Setup build tools**: Publishes `package.json`, `vite.config.js`, `tailwind.config.js`, etc.
- **Update User model**: Adds subscription traits to your User model
- **Publish configuration**: Package configuration files

Then build your frontend assets:

```bash
npm install && npm run build
```

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

## Authentication Flow

1. **Registration**: Users can register at `/register`
2. **Login**: Users can login at `/login`
3. **Email Verification**: Optional email verification flow
4. **Password Reset**: Full password reset functionality
5. **Subscription Access**: After login, users are redirected to subscription plans

## File Structure

After running the install command, authentication files are published to:

```
resources/
├── js/
│   ├── Pages/
│   │   └── Auth/
│   │       ├── Login.vue
│   │       ├── Register.vue
│   │       ├── ForgotPassword.vue
│   │       ├── ResetPassword.vue
│   │       └── VerifyEmail.vue
│   ├── Components/
│   │   └── Auth/
│   │       └── AuthLayout.vue
│   └── app.js
├── css/
│   └── app.css
└── views/
    └── app.blade.php
```

## Customization

### Styling

The authentication pages use Tailwind CSS classes. You can customize the styling by:

1. Running the install command: `php artisan subscription:install-auth`
2. Modifying the Vue components in `resources/js/Pages/Auth/` and `resources/js/Components/Auth/`

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

After setting up authentication:

1. Run migrations: `php artisan migrate`
2. Seed some plans: `php artisan subscription:seed-plans`
3. Visit `/login` to test the authentication
4. Visit `/subscription/plans` to see the subscription interface
