<?php

use Illuminate\Support\Facades\Route;
use RiaanZA\LaravelSubscription\Http\Controllers\SubscriptionPlanController;
use RiaanZA\LaravelSubscription\Http\Controllers\SubscriptionController;
use RiaanZA\LaravelSubscription\Http\Controllers\PaymentController;
use RiaanZA\LaravelSubscription\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| Subscription Web Routes
|--------------------------------------------------------------------------
|
| Here are the web routes for the subscription package.
|
*/

$routeConfig = config('laravel-subscription.routes');
$prefix = $routeConfig['prefix'] ?? 'subscription';
$middleware = $routeConfig['middleware'] ?? ['web', 'auth'];

Route::prefix($prefix)->middleware($middleware)->group(function () {

    // Subscription Plans Routes
    Route::get('/plans', [SubscriptionPlanController::class, 'index'])
        ->name('subscription.plans.index');

    Route::get('/plans/{slug}', [SubscriptionPlanController::class, 'show'])
        ->name('subscription.plans.show');

    // Subscription Management Routes
    Route::get('/dashboard', [SubscriptionController::class, 'index'])
        ->name('subscription.dashboard');

    Route::post('/subscribe', [SubscriptionController::class, 'store'])
        ->name('subscription.store');

    Route::put('/subscription/{subscription}', [SubscriptionController::class, 'update'])
        ->name('subscription.update');

    Route::delete('/subscription/{subscription}/cancel', [SubscriptionController::class, 'cancel'])
        ->name('subscription.cancel');

    Route::post('/subscription/{subscription}/resume', [SubscriptionController::class, 'resume'])
        ->name('subscription.resume');

    // Payment Routes
    Route::get('/checkout/{planSlug}', [PaymentController::class, 'checkout'])
        ->name('subscription.checkout');

    Route::post('/payment/process', [PaymentController::class, 'process'])
        ->name('subscription.payment.process');

    Route::get('/payment/success', [PaymentController::class, 'success'])
        ->name('subscription.payment.success');

    Route::get('/payment/cancelled', [PaymentController::class, 'cancelled'])
        ->name('subscription.payment.cancelled');

    Route::get('/payment/failed', [PaymentController::class, 'failed'])
        ->name('subscription.payment.failed');

    // Payment Methods Management
    Route::get('/payment-methods', [PaymentController::class, 'paymentMethods'])
        ->name('subscription.payment.methods');

    Route::post('/payment-methods', [PaymentController::class, 'addPaymentMethod'])
        ->name('subscription.payment.methods.add');

    Route::delete('/payment-methods/{paymentMethodId}', [PaymentController::class, 'removePaymentMethod'])
        ->name('subscription.payment.methods.remove');

    Route::put('/payment-methods/default', [PaymentController::class, 'updateDefaultPaymentMethod'])
        ->name('subscription.payment.methods.default');
});

// Public routes (no authentication required)
Route::prefix($prefix)->middleware(['web'])->group(function () {

    // Public plan listing (for marketing pages)
    Route::get('/plans/public', [SubscriptionPlanController::class, 'index'])
        ->name('subscription.plans.public');
});

// Webhook routes (no authentication or CSRF protection)
Route::prefix('webhooks')->middleware(['api'])->group(function () {
    Route::post('/peach-payments', [WebhookController::class, 'peachPayments'])
        ->name('subscription.webhooks.peach-payments');
});
