<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('renders every branded error page accessibly in both directions and sizes', function (int $status, string $locale, int $width, int $height): void {
    config(['app.debug' => false]);
    Route::middleware('web')->get("/_error/{$status}", static fn () => abort($status));
    $this->withSession(['locale' => $locale]);

    $page = $this->visit("/_error/{$status}")->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertSee(__("errors.{$status}_title", [], $locale))->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => [document.documentElement.lang, document.documentElement.dir]'))->toBe([$locale, $locale === 'ar' ? 'rtl' : 'ltr']);
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P1-10');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P1-10/error-{$status}-{$locale}-{$width}");
})->with([403, 419, 429, 500, 503])->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])
    ->group('browser', 'T-P1-10', 'A2', 'YG-GAP-OPS-004');
