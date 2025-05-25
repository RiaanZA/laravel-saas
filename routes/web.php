<?php

use Illuminate\Support\Facades\Route;
use RiaanZA\LaravelSubscription\Http\Controllers\SubscriptionPlanController;

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
});

// Public routes (no authentication required)
Route::prefix($prefix)->middleware(['web'])->group(function () {
    
    // Public plan listing (for marketing pages)
    Route::get('/plans/public', [SubscriptionPlanController::class, 'index'])
        ->name('subscription.plans.public');
});
