<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('profile')->name('profile.')->middleware(['auth', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class])->group(function (): void {
    Route::patch('/', [ProfileController::class, 'update'])->name('update');
    Route::patch('/certificate-names', [ProfileController::class, 'certificateNames'])->name('certificate-names.update');
    Route::put('/password', [ProfileController::class, 'password'])->name('password.update');
    Route::post('/avatar', [ProfileController::class, 'avatar'])->name('avatar.update');
});
