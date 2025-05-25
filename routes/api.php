<?php

use Illuminate\Support\Facades\Route;
use RiaanZA\LaravelSubscription\Http\Controllers\SubscriptionPlanController;
use RiaanZA\LaravelSubscription\Http\Controllers\SubscriptionController;
use RiaanZA\LaravelSubscription\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| Subscription API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for the subscription package.
|
*/

$routeConfig = config('laravel-subscription.routes');
$prefix = $routeConfig['api_prefix'] ?? 'api/subscription';
$middleware = $routeConfig['api_middleware'] ?? ['api', 'auth:sanctum'];

Route::prefix($prefix)->middleware($middleware)->group(function () {

    // Subscription Plans API
    Route::get('/plans', [SubscriptionPlanController::class, 'index']);
    Route::get('/plans/{slug}', [SubscriptionPlanController::class, 'show']);

    // Subscription Management API
    Route::get('/current', [SubscriptionController::class, 'index']);
    Route::post('/subscribe', [SubscriptionController::class, 'store']);
    Route::put('/subscription/{subscription}', [SubscriptionController::class, 'update']);
    Route::delete('/subscription/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/subscription/{subscription}/resume', [SubscriptionController::class, 'resume']);

    // Usage Tracking API
    Route::get('/usage', [SubscriptionController::class, 'usage']);

    // Payment API
    Route::post('/payment/process', [PaymentController::class, 'process']);
    Route::get('/payment-methods', [PaymentController::class, 'paymentMethods']);
    Route::post('/payment-methods', [PaymentController::class, 'addPaymentMethod']);
    Route::delete('/payment-methods/{paymentMethodId}', [PaymentController::class, 'removePaymentMethod']);
    Route::put('/payment-methods/default', [PaymentController::class, 'updateDefaultPaymentMethod']);
});

// Public API routes (no authentication required)
Route::prefix($prefix)->middleware(['api'])->group(function () {

    // Public plans endpoint
    Route::get('/public/plans', [SubscriptionPlanController::class, 'index']);
});
