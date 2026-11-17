<?php

declare(strict_types=1);

use App\Http\Controllers\CmsController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\PublicCourseController;
use App\Http\Controllers\PublicCoursePreviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\ForgetPublicLocale;
use Illuminate\Support\Facades\Route;

// T-P2-06: crawler files and the locale-prefixed public URLs of ED-018 (/ar/..., /en/...). The unprefixed routes in web.php keep
// working (locale from session/cookie/browser); their canonical tag points at the prefixed URL.
Route::get('/robots.txt', RobotsController::class)->name('seo.robots');
Route::get('/sitemap.xml', SitemapController::class)->name('seo.sitemap');

Route::prefix('{publicLocale}')
    ->where(['publicLocale' => implode('|', config('app.supported_locales'))])
    ->middleware(ForgetPublicLocale::class)
    ->name('public.')
    ->group(static function (): void {
        Route::get('/', [CmsController::class, 'home'])->name('home');
        Route::get('/courses', PublicCatalogController::class)->name('catalog.index');
        Route::get('/courses/{course}', PublicCourseController::class)->name('catalog.show');
        Route::get('/courses/{course}/preview/{item}', PublicCoursePreviewController::class)->whereNumber('item')->name('catalog.preview');
        Route::get('/pages/{slug}', [CmsController::class, 'show'])->name('cms.show');
    });
