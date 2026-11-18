<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$workspaceViewports = [
    ['ar', 'light', 390, 844], ['ar', 'dark', 390, 844],
    ['en', 'light', 390, 844], ['en', 'dark', 390, 844],
    ['ar', 'light', 1440, 900], ['ar', 'dark', 1440, 900],
    ['en', 'light', 1440, 900], ['en', 'dark', 1440, 900],
];

it('renders each native sign-in screen in the shared visual system', function (string $panel, string $locale, string $theme, int $width, int $height) {
    $this->withSession(['locale' => $locale]);
    $page = $this->visit('/'.$panel.'/login')->resize($width, $height);
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertVisible('.yg-auth-layout')->assertVisible('.yg-auth-card')
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => getComputedStyle(document.querySelector(".yg-auth-layout")).display'))->toBe('grid');
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $directory = base_path('tests/Browser/Screenshots/T-P1-08/reference-pass/surfaces');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P1-08/reference-pass/surfaces/{$panel}-login-{$locale}-{$theme}-{$width}");
    $page->keys('.yg-locale-button', 'Tab');
    expect($page->script('() => document.activeElement !== document.body'))->toBeTrue();
})->with(['admin', 'instructor', 'finance'])->with($workspaceViewports)->group('browser', 'T-P1-08', 'A4', 'D-026');

it('renders every panel welcome screen without fabricated module data', function (string $panel, string $locale, string $theme, int $width, int $height) {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create(['locale' => $locale, 'name' => $locale === 'ar' ? 'مدير النظام' : 'System administrator'])->assignRole('super_admin'));
    $page = $this->visit('/'.$panel)->resize($width, $height);
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');
    if ($panel === 'admin') {
        $page->assertVisible('.yg-summary-strip')->assertVisible('[data-dashboard-manage-courses]');
    } else {
        $page->assertVisible('.yg-dashboard-intro')->assertNotPresent('.yg-summary-strip');
        expect($page->script('() => getComputedStyle(document.querySelector(".yg-dashboard-intro")).display'))->toBe('flex');
    }
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $directory = base_path('tests/Browser/Screenshots/T-P1-08/reference-pass/surfaces');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P1-08/reference-pass/surfaces/{$panel}-dashboard-{$locale}-{$theme}-{$width}");
})->with(['admin', 'instructor', 'finance'])->with($workspaceViewports)->group('browser', 'T-P1-08', 'A4', 'D-026');
