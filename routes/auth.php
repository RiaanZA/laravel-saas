<?php

use Illuminate\Support\Facades\Route;
use RiaanZA\LaravelSubscription\Http\Controllers\Auth\LoginController;
use RiaanZA\LaravelSubscription\Http\Controllers\Auth\RegisterController;
use RiaanZA\LaravelSubscription\Http\Controllers\Auth\ForgotPasswordController;
use RiaanZA\LaravelSubscription\Http\Controllers\Auth\ResetPasswordController;
use RiaanZA\LaravelSubscription\Http\Controllers\Auth\EmailVerificationController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Here are the authentication routes for the subscription package.
|
*/

Route::middleware(['web', 'guest'])->group(function () {
    // Login Routes
    Route::get('login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('login', [LoginController::class, 'store']);

    // Registration Routes
    Route::get('register', [RegisterController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisterController::class, 'store']);

    // Password Reset Routes
    Route::get('forgot-password', [ForgotPasswordController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [ResetPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware(['web', 'auth'])->group(function () {
    // Email Verification Routes
    Route::get('verify-email', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Logout Route
    Route::post('logout', [LoginController::class, 'destroy'])
        ->name('logout');
});
