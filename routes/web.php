<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/up');

Route::get('/_design/tokens', function (Request $request) {
    abort_unless(app()->environment('local', 'staging') && config('design.token_preview'), 404);

    $locale = $request->query('locale') === 'ar' ? 'ar' : 'en';
    $theme = $request->query('theme') === 'dark' ? 'dark' : 'light';
    app()->setLocale($locale);

    return response()->view('design.tokens', compact('locale', 'theme'))
        ->header('Cache-Control', 'private, no-store');
})->name('design.tokens');
