<?php

declare(strict_types=1);

it('renders the component gallery accessibly in both directions', function (
    string $locale,
    string $direction,
    string $theme,
    int $width,
    int $height,
) {
    $this->app['env'] = 'staging';
    config(['design.token_preview' => true]);

    $page = $this->visit("/_design/components?locale={$locale}&theme={$theme}")
        ->resize($width, $height)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('() => [document.documentElement.lang, document.documentElement.dir, document.documentElement.dataset.theme]'))
        ->toBe([$locale, $direction, $theme]);
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $directory = base_path('tests/Browser/Screenshots/T-P1-09');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "T-P1-09/{$locale}-{$theme}-{$width}");
})->with([
    ['ar', 'rtl', 'light', 390, 844],
    ['ar', 'rtl', 'light', 1440, 900],
    ['ar', 'rtl', 'dark', 390, 844],
    ['ar', 'rtl', 'dark', 1440, 900],
    ['en', 'ltr', 'light', 390, 844],
    ['en', 'ltr', 'light', 1440, 900],
    ['en', 'ltr', 'dark', 390, 844],
    ['en', 'ltr', 'dark', 1440, 900],
])->group('browser', 'T-P1-09', 'A3', 'YG-GAP-A11Y-001', 'YG-GAP-QA-003');

it('opens and closes a modal and selects tabs using the reading direction', function (string $locale, string $key) {
    $this->app['env'] = 'staging';
    config(['design.token_preview' => true]);

    $page = $this->visit("/_design/components?locale={$locale}");
    $page->click('[commandfor="gallery-modal"]')->assertVisible('#gallery-modal[open]');
    $page->click('#gallery-modal button[aria-label]')->assertNotPresent('#gallery-modal[open]');
    $page->click('#gallery-tabs-tab-overview');
    $page->script("() => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', {key: '{$key}', bubbles: true}))");

    expect($page->script('() => document.activeElement.id'))->toBe('gallery-tabs-tab-examples');
    $page->assertVisible('#gallery-tabs-panel-examples');
})->with([['ar', 'ArrowLeft'], ['en', 'ArrowRight']])->group('browser', 'T-P1-09', 'A1', 'YG-GAP-A11Y-001');
