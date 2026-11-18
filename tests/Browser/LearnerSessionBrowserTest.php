<?php

declare(strict_types=1);

it('displays the learner session notice on the localized sign-in screen', function (string $locale, int $width, int $height, string $theme): void {
    $this->withSession(['locale' => $locale, 'learner_session_notice' => true]);
    $page = $this->visit('/login')->resize($width, $height);
    $page->script("() => localStorage.setItem('theme', '{$theme}')");
    $page->refresh();
    $page->assertSee(__('auth.learner_session_ended', [], $locale))
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.dataset.theme'))->toBe($theme)
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    $directory = base_path('tests/Browser/Screenshots/T-P4-07');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P4-07/session-ended-{$locale}-{$width}-{$theme}");
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])->with(['light', 'dark'])
    ->group('browser', 'T-P4-07', 'A1', 'YG-GAP-SEC-001');
