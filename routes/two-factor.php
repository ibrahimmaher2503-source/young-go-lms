<?php

declare(strict_types=1);

use App\Http\Controllers\TwoFactorController;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureActiveAccount::class, 'auth.session'])->group(function (): void {
    Route::get('/two-factor-challenge', [TwoFactorController::class, 'challengePage'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->middleware('throttle:two-factor')->name('two-factor.verify');

    // Enrolled users must pass the challenge first; users who still have to enroll may open these pages.
    Route::middleware(EnforceTwoFactor::class.':setup')->prefix('security/two-factor')->name('two-factor.')->group(function (): void {
        Route::get('/', [TwoFactorController::class, 'setupPage'])->name('setup');
        Route::post('/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:two-factor')->name('confirm');
        Route::post('/recovery-codes', [TwoFactorController::class, 'regenerate'])->middleware('throttle:6,1')->name('recovery-codes');
        Route::delete('/', [TwoFactorController::class, 'disable'])->middleware('throttle:6,1')->name('disable');
    });
});
