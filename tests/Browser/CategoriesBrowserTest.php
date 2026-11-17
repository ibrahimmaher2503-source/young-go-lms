<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the category workspace in both languages and screen sizes', function (string $locale, string $theme, int $width, int $height) {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::factory()->create(['locale' => $locale])->assignRole('admin'));
    $this->withSession(['locale' => $locale]);

    $page = $this->visit('/admin/categories')->resize($width, $height);
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertSee(__('categories.title', [], $locale))->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P2-01');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P2-01/categories-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('browser', 'T-P2-01', 'A1');
