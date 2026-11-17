<?php

declare(strict_types=1);

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\GuardianController;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class])->group(function (): void {
    Route::post('/confirm-password', [AuthenticationController::class, 'confirmPassword'])->middleware('throttle:6,1')->name('password.confirm.store');

    Route::prefix('guardian')->name('guardian.')->group(function (): void {
        Route::post('/link-codes', [GuardianController::class, 'issueCode'])->middleware('throttle:6,1')->name('link-codes.store');
        Route::post('/children', [GuardianController::class, 'createChild'])->middleware('throttle:6,1')->name('children.store');
        Route::post('/children/{child}/password', [GuardianController::class, 'resetChildPassword'])->whereNumber('child')->middleware(['password.confirm', 'throttle:6,1'])->name('children.password');
        Route::post('/links', [GuardianController::class, 'redeem'])->middleware('throttle:6,1')->name('links.store');
        Route::post('/links/{link}/confirm', [GuardianController::class, 'confirm'])->whereNumber('link')->name('links.confirm');
        Route::post('/context/{child}', [GuardianController::class, 'enterContext'])->whereNumber('child')->middleware('password.confirm')->name('context.enter');
        Route::delete('/context', [GuardianController::class, 'exitContext'])->name('context.exit');
    });
});
