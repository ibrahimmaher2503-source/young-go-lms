<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Copy temporarily to tests/Browser/UIHeaderTest.php for Pest discovery; remove that copy afterward.
uses(RefreshDatabase::class);

it('keeps the mobile learning header on one row with an accessible full account name', function (string $role, string $locale, string $theme, int $width): void {
    $this->seed(RolePermissionSeeder::class);
    $name = $locale === 'ar' ? 'سارة عبد الرحمن' : 'Sara Abdelrahman';
    $user = User::factory()->create(['locale' => $locale, 'name' => $name])->assignRole($role);
    $this->withSession(['locale' => $locale])->actingAs($user);
    $page = $this->visit($role === 'parent' ? '/parent' : '/my')->resize($width, 844);
    $page->script("() => localStorage.setItem('theme', '{$theme}')");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');
    $metrics = $page->script(<<<'JS'
        () => {
            const account = document.querySelector('[popovertarget="account-menu"]');
            return {header: document.querySelector('header').getBoundingClientRect().height, account: account.getBoundingClientRect().height, label: account.textContent.trim(), overflow: document.documentElement.scrollWidth > innerWidth};
        }
        JS);
    expect($metrics['header'])->toBeLessThanOrEqual(72)
        ->and($metrics['account'])->toBeLessThanOrEqual(52)
        ->and($metrics['label'])->toBe($name)
        ->and($metrics['overflow'])->toBeFalse();
    $page->click('[popovertarget="account-menu"]')->assertVisible('#account-menu')
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
})->with(['parent', 'student'])->with(['ar', 'en'])->with(['light', 'dark'])->with([360, 390])
    ->group('browser', 'T-UI-01', 'D-026', 'YG-GAP-A11Y-001');
