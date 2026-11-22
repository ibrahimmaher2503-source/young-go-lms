<?php

declare(strict_types=1);

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\GuardianVerificationController;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class])->group(function (): void {
    Route::get('/confirm-password', static fn () => redirect('/family'))->name('password.confirm');
    Route::post('/confirm-password', [AuthenticationController::class, 'confirmPassword'])->middleware('throttle:6,1,password-confirm')->name('password.confirm.store');

    Route::prefix('guardian')->name('guardian.')->group(function (): void {
        Route::post('/link-codes', [GuardianController::class, 'issueCode'])->middleware('throttle:6,1,guardian-link-codes')->name('link-codes.store');
        Route::post('/children', [GuardianController::class, 'createChild'])->middleware('throttle:6,1,guardian-children')->name('children.store');
        Route::post('/children/{child}/password', [GuardianController::class, 'resetChildPassword'])->whereNumber('child')->middleware(['password.confirm', 'throttle:6,1,guardian-child-password'])->name('children.password');
        Route::post('/verification/code', [GuardianVerificationController::class, 'send'])->middleware('throttle:6,1,guardian-verification-send')->name('verification.send');
        Route::post('/verification', [GuardianVerificationController::class, 'confirm'])->middleware('throttle:10,1,guardian-verification-confirm')->name('verification.confirm');
        Route::post('/links', [GuardianController::class, 'redeem'])->middleware('throttle:6,1,guardian-links')->name('links.store');
        Route::post('/links/{link}/confirm', [GuardianController::class, 'confirm'])->whereNumber('link')->middleware('throttle:6,1,guardian-links-confirm')->name('links.confirm');
        Route::post('/context/{child}', [GuardianController::class, 'enterContext'])->whereNumber('child')->middleware('password.confirm')->name('context.enter');
        Route::delete('/context', [GuardianController::class, 'exitContext'])->name('context.exit');
    });
});
