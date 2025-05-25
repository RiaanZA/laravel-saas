<?php

use Illuminate\Support\Facades\Route;
use RiaanZA\LaravelSubscription\Http\Controllers\SubscriptionPlanController;

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
});

// Public API routes (no authentication required)
Route::prefix($prefix)->middleware(['api'])->group(function () {
    
    // Public plans endpoint
    Route::get('/public/plans', [SubscriptionPlanController::class, 'index']);
});
