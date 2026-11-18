<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('renders staff users and roles in both directions and themes', function (string $locale, string $theme, int $width, int $height) {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['locale' => $locale, 'name' => $locale === 'ar' ? 'مدير النظام' : 'System administrator', 'email' => 'admin@example.test'])->assignRole('super_admin');
    foreach ([['سارة أحمد', 'Sara Ahmed', 'student'], ['عمر حسن', 'Omar Hassan', 'parent'], ['نور محمد', 'Nour Mohamed', 'instructor_academy'], ['ليلى علي', 'Layla Ali', 'student']] as $index => [$arabic, $english, $role]) {
        User::factory()->create(['name' => $locale === 'ar' ? $arabic : $english, 'email' => 'learner'.$index.'@example.test', 'status' => $index === 3 ? 'suspended' : 'active'])->assignRole($role);
    }
    Activity::query()->create(['log_name' => 'audit', 'description' => 'Role assignment', 'event' => 'user.roles_changed', 'causer_type' => User::class, 'causer_id' => $user->id]);
    $this->actingAs($user);

    $screenshots = base_path('tests/Browser/Screenshots/T-P1-08/reference-pass');
    if (! is_dir($screenshots)) {
        mkdir($screenshots, 0755, true);
    }

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $path = parse_url($resource::getUrl(panel: 'admin'), PHP_URL_PATH);
        $screen = substr($path, strlen('/admin/'));
        if ($width === 1568 && $screen !== 'staff-users') {
            continue;
        }
        $page = $this->visit($path)->resize($width, $height);
        $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
        $page->refresh();
        $page->script('async () => { await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); }');
        expect($page->script('() => [...document.querySelectorAll(".fi-ta-content-ctn")].every(region => region.scrollWidth <= region.clientWidth + 1 || region.tabIndex === 0)'))->toBeTrue($screen);
        $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
        expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
        expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
        expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        if ($width === 390) {
            expect($page->script('() => { const avatar = document.querySelector(".fi-topbar .fi-user-menu-trigger").getBoundingClientRect(); return avatar.left >= 8 && avatar.right <= innerWidth - 8; }'))->toBeTrue();
        }
        $page->assertVisible('.yg-workspace-page');
        if ($screen === 'staff-users') {
            $page->assertVisible('.yg-summary-strip')->assertVisible('.yg-workspace-sidebar');
            expect($page->script('() => document.querySelectorAll(".yg-summary-item").length'))->toBe(3);
            expect($page->script('() => new Set([...document.querySelectorAll(".yg-role-meter")].map(meter => meter.getBoundingClientRect().width)).size'))->toBe(1);
            if ($page->script('() => document.querySelector(".fi-ta-content-ctn").tabIndex === 0')) {
                $page->assertVisible('.yg-table-scroll-hint');
            }
            if ($width === 390) {
                expect($page->script('() => [...document.querySelectorAll(".fi-tabs-item-label")].every(label => label.scrollWidth <= label.clientWidth + 1)'))->toBeTrue();
                $page->assertVisible('.yg-table-scroll-hint');
                expect($page->script('() => document.querySelector(".yg-workspace-sidebar").getBoundingClientRect().top >= document.querySelector(".yg-workspace-main").getBoundingClientRect().bottom'))->toBeTrue();
            }
        }
        if ($width === 390 && $screen === 'role-permissions') {
            expect($page->script('() => { const table = document.querySelector(".fi-ta-content-ctn"); return table.scrollWidth <= table.clientWidth + 1; }'))->toBeTrue();
        }
        $filename = str_replace('/', '-', $screen);
        $page->screenshot(fullPage: $width !== 1568, filename: "T-P1-08/reference-pass/{$filename}-{$locale}-{$theme}-{$width}");
        if ($screen === 'role-permissions') {
            $role = Role::findByName('finance', 'web');
            $action = '[data-workspace-permission-action="'.$role->id.'"]';
            $selector = json_encode($action, JSON_THROW_ON_ERROR);
            $page->script("() => document.querySelector({$selector}).scrollIntoView({block: 'center'})");
            $page->click($action)->assertVisible('.fi-modal-window:visible');
            $page->script('async () => { await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); await Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {}))); await document.fonts.ready; }');
            $page->assertSee(__('permissions.payment.view', [], $locale))->assertSee(__('staff.permissions_scroll_help', [], $locale))->assertNoAccessibilityIssues();
            $page->screenshot(fullPage: false, filename: "T-P1-08/reference-pass/permission-editor-{$locale}-{$theme}-{$width}");
            $page->keys('.fi-modal-window:visible', 'Tab');
            expect($page->script('() => !!document.activeElement.closest(".fi-modal-window")'))->toBeTrue();
            $page->keys('.fi-modal-window:visible', 'Shift+Tab');
            expect($page->script('() => !!document.activeElement.closest(".fi-modal-window")'))->toBeTrue();
            $page->keys('.fi-modal-window:visible', 'Escape')->assertNotPresent('.fi-modal-window:visible');
            $page->assertVisible($action.':focus');
        }
        $page->keys('.yg-locale-button', 'Tab');
        expect($page->script('() => document.activeElement !== document.body'))->toBeTrue();
        if ($width === 1568 && $screen === 'staff-users') {
            expect($page->script('() => [...document.querySelectorAll(".fi-sidebar-item-label")].filter(label => label.clientWidth > 0).every(label => label.scrollWidth <= label.clientWidth + 1)'))->toBeTrue();
            expect($page->script('() => [...document.querySelectorAll(".yg-email > span")].every(email => email.scrollWidth <= email.clientWidth + 1)'))->toBeTrue();
            $metrics = $page->script("() => ({sidebar: document.querySelector('.fi-sidebar').getBoundingClientRect().width, topbar: document.querySelector('.fi-topbar').getBoundingClientRect().height, heading: getComputedStyle(document.querySelector('.fi-header-heading')).fontSize, summary: document.querySelector('.yg-summary-strip').getBoundingClientRect().height, main: document.querySelector('.yg-workspace-main').getBoundingClientRect().width, aside: document.querySelector('.yg-workspace-sidebar').getBoundingClientRect().width, tableFits: document.querySelector('.fi-ta-content-ctn').scrollWidth <= document.querySelector('.fi-ta-content-ctn').clientWidth + 1})");
            $contract = json_decode(file_get_contents(base_path('docs/design-system/visual-contract.json')), true, flags: JSON_THROW_ON_ERROR);
            expect(abs($metrics['sidebar'] - $contract['sidebar_width']))->toBeLessThanOrEqual($contract['geometry_tolerance_px'])
                ->and(abs($metrics['topbar'] - $contract['topbar_height']))->toBeLessThanOrEqual($contract['geometry_tolerance_px'])
                ->and($metrics['heading'])->toBe($contract['heading_font_size'])
                ->and(abs($metrics['summary'] - $contract['summary_height']))->toBeLessThanOrEqual($contract['geometry_tolerance_px'])
                ->and(abs($metrics['main'] - $contract['table_card_width']))->toBeLessThanOrEqual($contract['geometry_tolerance_px'])
                ->and(abs($metrics['aside'] - $contract['sidebar_card_width']))->toBeLessThanOrEqual($contract['geometry_tolerance_px'])
                ->and($metrics['tableFits'])->toBeTrue();
            $rowHeights = $page->script('() => [...document.querySelectorAll(".fi-ta-records > .fi-ta-row")].map(row => row.getBoundingClientRect().height)');
            file_put_contents($screenshots.'/workspace-reference-metrics.json', json_encode([...$metrics, 'rowHeights' => $rowHeights], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            expect($rowHeights)->toHaveCount(5);
            foreach ($rowHeights as $height) {
                expect(abs($height - $contract['user_table_row_height']))->toBeLessThanOrEqual($contract['user_table_row_tolerance_px']);
            }
        }
    }
})->with([
    ['ar', 'light', 390, 844], ['ar', 'dark', 390, 844],
    ['en', 'light', 390, 844], ['en', 'dark', 390, 844],
    ['ar', 'light', 1440, 900], ['ar', 'dark', 1440, 900],
    ['en', 'light', 1440, 900], ['en', 'dark', 1440, 900],
    ['ar', 'light', 1568, 1003],
])->group('browser', 'T-P1-08', 'A4');

it('keeps the mobile staff table keyboard reachable after search updates', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create(['locale' => 'en'])->assignRole('super_admin'));
    User::factory()->create(['name' => 'Sara Ahmed'])->assignRole('student');

    $page = $this->visit('/admin/staff-users')->resize(390, 844);
    $search = '.fi-ta-search-field input';
    $page->assertPresent('.fi-ta-content-ctn[tabindex="0"]')
        ->fill($search, 'no matching staff users')
        ->assertNotPresent('.fi-ta-records > .fi-ta-row')
        ->fill($search, 'Sara Ahmed')
        ->assertSee('Sara Ahmed')
        ->assertPresent('.fi-ta-content-ctn[tabindex="0"][role="region"]')
        ->assertNoAccessibilityIssues();
})->group('browser', 'T-P1-08', 'A4', 'D-026');
