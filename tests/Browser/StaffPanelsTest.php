<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders staff users and roles in both directions and themes', function (string $locale, string $theme, int $width, int $height) {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['locale' => $locale])->assignRole('super_admin');
    $this->actingAs($user);

    $screenshots = base_path('tests/Browser/Screenshots/T-P1-08');
    if (! is_dir($screenshots)) {
        mkdir($screenshots, 0755, true);
    }

    foreach (['staff-users', 'role-permissions'] as $screen) {
        $page = $this->visit('/admin/'.$screen)->resize($width, $height);
        $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
        $page->refresh()->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
        expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
        expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
        expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        $page->screenshot(fullPage: false, filename: "T-P1-08/{$screen}-{$locale}-{$theme}-{$width}");
    }
})->with([
    ['ar', 'light', 390, 844], ['ar', 'dark', 390, 844],
    ['en', 'light', 390, 844], ['en', 'dark', 390, 844],
    ['ar', 'light', 1440, 900], ['ar', 'dark', 1440, 900],
    ['en', 'light', 1440, 900], ['en', 'dark', 1440, 900],
])->group('browser', 'T-P1-08', 'A4');
