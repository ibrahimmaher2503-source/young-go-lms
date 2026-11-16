<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::post('/locale', LocaleController::class)->middleware([EnsureActiveAccount::class, 'auth.session'])->name('locale.update');
