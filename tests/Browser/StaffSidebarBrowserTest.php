<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps staff priorities, permissions and destinations usable in the sidebar', function (string $panel, string $role, string $locale, string $theme, int $width, int $height): void {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create(['locale' => $locale])->assignRole($role));
    $sidebar = '#fi-main-sidebar';
    $page = $this->visit('/'.$panel)->resize($width, $height);
    $page->script("() => localStorage.setItem('filament:theme:mode', '{$theme}')");
    $page->refresh();

    if ($width < 768) {
        $page->click('.fi-topbar-open-sidebar-btn');
    }

    $queue = $panel === 'finance' ? 'sales' : 'reviews';
    $label = __('navigation.groups.'.$queue, [], $locale);
    $group = $sidebar.' [data-group-label="'.$label.'"]';
    $page->assertVisible($group.' button[aria-expanded="true"]');
    $destination = $panel === 'finance' ? '/finance/manual-payments' : '/'.$panel.'/courses/create';
    $link = $sidebar.' a[href$="'.$destination.'"]';
    $page->assertVisible($link)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue()
        ->and($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');

    $directory = storage_path('app/review-media/staff-sidebar');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "../../../storage/app/review-media/staff-sidebar/{$panel}-{$locale}-{$theme}-{$width}");
    if ($panel === 'admin' && $locale === 'ar' && $theme === 'light' && $width === 1440) {
        $page->resize(1568, 1003)->screenshot(fullPage: false, filename: '../../../storage/app/review-media/staff-sidebar/admin-ar-light-1568');
    }

    $page->click($link)->assertPathIs($destination);
    if ($width >= 768) {
        $page->assertVisible($link.'[aria-current="page"]');
        if ($panel !== 'finance') {
            $page->assertNotPresent($sidebar.' a[href$="/'.$panel.'/courses"][aria-current="page"]');
        }
    }
})->with([
    ['admin', 'admin'],
    ['finance', 'finance'],
    ['instructor', 'instructor_academy'],
])->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('browser', 'T-P9-09', 'T-SIDEBAR-V2', 'A1');
