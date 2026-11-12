<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Middleware\EnsureCurrentAuthVersion;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1,auth-register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1,auth-login');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1,auth-forgot');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1,auth-reset');

    Route::middleware(['auth:api', EnsureCurrentAuthVersion::class])->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed:relative')
            ->name('verification.verify');
        Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:5,1,auth-verify-resend');
    });
});
