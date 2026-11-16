<?php

declare(strict_types=1);

use App\Http\Controllers\AuthenticationController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthenticationController::class, 'register'])->middleware('throttle:register')->name('register');
Route::post('/login', [AuthenticationController::class, 'login'])->middleware('throttle:login')->name('login');
Route::post('/forgot-password', [AuthenticationController::class, 'forgotPassword'])->middleware('throttle:password-reset')->name('password.email');
Route::post('/reset-password', [AuthenticationController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('password.update');
// T-P1-10 supplies the form served at the notification's reset URL.
Route::get('/reset-password/{token}', static fn () => response()->noContent()->header('Cache-Control', 'no-store'))->name('password.reset');

Route::middleware(['auth', EnsureActiveAccount::class, 'auth.session'])->group(function (): void {
    Route::post('/logout', [AuthenticationController::class, 'logout'])->name('logout');
    Route::get('/verify-email/{id}/{hash}', [AuthenticationController::class, 'verifyEmail'])->middleware('signed', 'throttle:6,1')->name('verification.verify');
    Route::post('/email/verification-notification', [AuthenticationController::class, 'resendVerification'])->middleware('throttle:6,1')->name('verification.send');
});
