<?php

declare(strict_types=1);

use App\Http\Controllers\CmsController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [CmsController::class, 'home'])->name('cms.home');
Route::get('/courses', PublicCatalogController::class)->name('catalog.index');
Route::get('/pages/{slug}', [CmsController::class, 'show'])->name('cms.show');
Route::get('/admin/cms-preview/{page}', [CmsController::class, 'preview'])
    ->middleware(['auth', 'signed', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class, 'can:content.page.manage'])
    ->name('cms.preview');

Route::get('/_design/tokens', function (Request $request) {
    abort_unless(app()->environment('local', 'staging') && config('design.token_preview'), 404);

    $locale = $request->query('locale') === 'ar' ? 'ar' : 'en';
    $theme = $request->query('theme') === 'dark' ? 'dark' : 'light';
    app()->setLocale($locale);

    return response()->view('design.tokens', compact('locale', 'theme'))
        ->header('Cache-Control', 'private, no-store');
})->name('design.tokens');
