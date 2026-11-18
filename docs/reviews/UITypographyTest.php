<?php

declare(strict_types=1);

// Copy temporarily to tests/Browser/UITypographyTest.php for Pest browser discovery; remove that copy afterward.

it('uses the shared body type scale with script specific rhythm without overflowing', function (string $locale, string $theme, int $width, int $height): void {
    $this->app['env'] = 'staging';
    config(['design.token_preview' => true]);
    $page = $this->visit("/_design/components?locale={$locale}&theme={$theme}")->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $metrics = $page->script(<<<'JS'
        () => {
            const body = getComputedStyle(document.body);
            return {size: parseFloat(body.fontSize), line: parseFloat(body.lineHeight), overflow: document.documentElement.scrollWidth > innerWidth};
        }
        JS);
    expect($metrics['size'])->toEqual(18)
        ->and($metrics['line'])->toBeGreaterThanOrEqual($locale === 'ar' ? 32.4 : 28.8)
        ->and($metrics['overflow'])->toBeFalse();
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('browser', 'T-UI-01', 'D-026', 'YG-GAP-A11Y-001');
