<?php

declare(strict_types=1);

use App\Http\Controllers\PageController;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::get('/login', [PageController::class, 'login'])->name('login.page');
Route::get('/register', [PageController::class, 'register'])->name('register.page');
Route::get('/forgot-password', [PageController::class, 'forgotPassword'])->name('password.request');
Route::get('/reset-password/{token}', [PageController::class, 'resetPassword'])->name('password.reset');

Route::middleware(['auth', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class])->group(function (): void {
    Route::get('/home', [PageController::class, 'home'])->name('home');
    Route::get('/email/verify', [PageController::class, 'verifyNotice'])->name('verification.notice');
    Route::get('/profile', [PageController::class, 'profile'])->name('profile.page');
    Route::get('/family', [PageController::class, 'family'])->name('family');
    Route::get('/link', [PageController::class, 'link'])->name('link');
});

// Unmatched URLs still pass through the web middleware so the branded 404 follows the selected locale.
Route::fallback(static fn () => abort(404));
