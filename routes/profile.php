<?php

declare(strict_types=1);

use App\Http\Controllers\NotificationPreferencesController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('profile')->name('profile.')->middleware(['auth', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class])->group(function (): void {
    Route::patch('/', [ProfileController::class, 'update'])->name('update');
    Route::get('/notifications', [NotificationPreferencesController::class, 'show'])->name('notifications.show');
    Route::patch('/notifications', [NotificationPreferencesController::class, 'update'])->name('notifications.update');
    Route::get('/privacy', [PrivacyController::class, 'show'])->name('privacy.show');
    Route::post('/privacy/requests', [PrivacyController::class, 'store'])->middleware('throttle:6,1')->name('privacy.store');
    Route::post('/privacy/requests/{dataRequest}/cancel', [PrivacyController::class, 'cancel'])->whereNumber('dataRequest')->name('privacy.cancel');
    Route::get('/privacy/requests/{dataRequest}/download', [PrivacyController::class, 'download'])->whereNumber('dataRequest')->middleware('throttle:certificate-download')->name('privacy.download');
    Route::patch('/certificate-names', [ProfileController::class, 'certificateNames'])->name('certificate-names.update');
    Route::put('/password', [ProfileController::class, 'password'])->name('password.update');
    Route::post('/avatar', [ProfileController::class, 'avatar'])->name('avatar.update');
});
