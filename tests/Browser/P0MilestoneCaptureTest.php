<?php

declare(strict_types=1);

it('renders an accessible token preview without horizontal overflow', function (
    string $locale,
    string $direction,
    string $theme,
    int $width,
    int $height,
) {
    $this->app['env'] = 'staging';
    config(['design.token_preview' => true]);

    $page = $this->visit("/_design/tokens?locale={$locale}&theme={$theme}")
        ->resize($width, $height)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('() => [document.documentElement.lang, document.documentElement.dir, document.documentElement.dataset.theme]'))
        ->toBe([$locale, $direction, $theme]);

    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $page->screenshot(fullPage: true, filename: "T-P0-MG/{$locale}-{$theme}-{$width}");
})->with([
    ['ar', 'rtl', 'light', 390, 844],
    ['ar', 'rtl', 'light', 1440, 900],
    ['ar', 'rtl', 'dark', 390, 844],
    ['ar', 'rtl', 'dark', 1440, 900],
    ['en', 'ltr', 'light', 390, 844],
    ['en', 'ltr', 'light', 1440, 900],
    ['en', 'ltr', 'dark', 390, 844],
    ['en', 'ltr', 'dark', 1440, 900],
])->group('browser', 'T-P0-04', 'A4', 'YG-GAP-UI-001');

it('shows the Laravel health page over the browser transport', function (): void {
    $this->visit('/up')->assertSee('Application up');
})->group('browser', 'T-P0-MG');
