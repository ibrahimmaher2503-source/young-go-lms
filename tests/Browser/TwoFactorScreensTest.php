<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateChildAccount;
use App\Domain\Identity\TwoFactorSession;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function tfBrowserUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['password' => 'Password1!secure', ...$attributes]);
    $user->assignRole($role);

    return $user;
}

function tfShot($page, string $name): void
{
    $directory = base_path('tests/Browser/screenshots/T-P1-11');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P1-11/{$name}");
}

it('requires staff enrollment through the admin panel and shows recovery codes once', function (string $locale): void {
    config(['identity.two_factor_enforced' => true]);
    $this->withSession(['locale' => $locale]);
    $this->actingAs(tfBrowserUser('admin', ['locale' => $locale]));

    $page = $this->visit('/admin')->assertPathIs('/security/two-factor')->assertPresent('[data-two-factor-secret]');
    expect($page->script("() => !!document.querySelector('[role=img] svg')"))->toBeTrue();
    $secret = trim((string) $page->text('[data-two-factor-secret]'));
    expect($secret)->not->toBeEmpty();

    $page->type('#code', app(Google2FA::class)->getCurrentOtp($secret))->type('#enroll-password', 'Password1!secure')
        ->click('main form button[type="submit"]')
        ->waitForText(__('pages.tf_recovery_title', [], $locale))
        ->assertNoJavaScriptErrors();
    expect($page->script('() => document.querySelectorAll("[data-result-list] li").length'))->toBe(8);

    $page->refresh()->assertSee(__('pages.tf_enabled', [], $locale))->assertNotPresent('[data-two-factor-secret]')
        ->assertNotPresent('#recovery-codes:not([hidden])');
    expect($page->script('() => document.querySelectorAll("[data-result-list] li").length'))->toBe(0);
    $page->navigate('/admin')->assertPathIs('/admin')->assertVisible('[data-dashboard-manage-courses]');
})->with(['ar', 'en'])->group('browser', 'T-P1-11', 'A1', 'YG-GAP-SEC-003');

it('blocks an enrolled staff session until it completes the two-factor challenge', function (string $locale): void {
    config(['identity.two_factor_enforced' => true]);
    $this->withSession(['locale' => $locale]);
    $user = tfBrowserUser('admin', ['locale' => $locale, 'two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
    $this->actingAs($user);

    $this->visit('/admin')->assertPathIs('/two-factor-challenge')->type('#code', app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'))
        ->click('main form[action$="/two-factor-challenge"] button[type="submit"]')
        ->assertVisible('[data-dashboard-manage-courses]')->assertPathIs('/admin')->assertNoJavaScriptErrors();
})->with(['ar', 'en'])->group('browser', 'T-P1-11', 'A1', 'YG-GAP-SEC-003');

it('lets a parent reset the child password in the browser and the child signs in with it', function (): void {
    $this->withSession(['locale' => 'en']);
    $parent = tfBrowserUser('parent');
    $child = app(CreateChildAccount::class)->handle($parent, [
        'username' => 'reset-kid', 'password' => 'OldChildPass1!', 'name_en' => 'Reset Kid', 'birth_year' => 2016, 'birth_month' => 3,
    ]);
    $this->actingAs($parent);

    $page = $this->visit('/family');
    $page->click("[commandfor=\"reset-{$child->id}\"]")
        ->type("#reset-child-password-{$child->id}", 'NewChildPass1!')->type("#reset-child-confirm-{$child->id}", 'NewChildPass1!')
        ->type("#reset-parent-password-{$child->id}", 'Password1!secure')
        ->click("#reset-{$child->id} form[data-ajax] button[type=\"submit\"]")
        ->waitForText(__('pages.reset_child_done'))->assertNoJavaScriptErrors();

    $this->postJson('/login', ['login' => 'reset-kid', 'password' => 'OldChildPass1!'])->assertUnprocessable();

    $page->refresh()->click('#account-menu-trigger, [popovertarget="account-menu"]');
    $page->click('#account-menu form[action$="/logout"] button[type="submit"]')->waitForText(__('pages.login_title'))->assertPathIs('/login');
    $page->type('#login', 'reset-kid')->type('#password', 'NewChildPass1!')
        ->click('main form button[type="submit"]')->waitForText(__('learner.my_enrolled'))->assertPathIs('/my')
        ->assertSee('Reset Kid')->assertNoJavaScriptErrors();
    $page->click('#account-menu-trigger, [popovertarget="account-menu"]');
    $page->click('#account-menu a[href$="/profile"]')->waitForText(__('pages.profile_title'))->assertPathIs('/profile')
        ->assertValue('#name_en', 'Reset Kid');
})->group('browser', 'T-P1-11', 'A3', 'YG-GAP-FAM-007');

it('renders the two-factor and child-reset screens accessibly in both directions and themes', function (string $screen, string $locale, int $width, int $height, string $theme) {
    $this->withSession(['locale' => $locale]);
    $enrolled = ['two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()];

    if ($screen === 'setup') {
        $this->actingAs(tfBrowserUser('admin', ['locale' => $locale, 'name_en' => 'Omar', 'display_name' => 'Omar']));
        $page = $this->visit('/security/two-factor');
    } elseif ($screen === 'enabled') {
        config(['identity.two_factor_enforced' => true]);
        $user = tfBrowserUser('admin', ['locale' => $locale, 'name_en' => 'Omar', 'display_name' => 'Omar', ...$enrolled]);
        $this->actingAs($user);
        TwoFactorSession::markVerified($user);
        $page = $this->visit('/security/two-factor');
    } elseif ($screen === 'challenge') {
        $this->actingAs(tfBrowserUser('admin', ['locale' => $locale, ...$enrolled]));
        $page = $this->visit('/two-factor-challenge');
    } else {
        $parent = tfBrowserUser('parent', ['name_en' => 'Mona', 'display_name' => 'Mona']);
        $child = app(CreateChildAccount::class)->handle($parent, ['username' => 'shot-kid', 'password' => 'ChildPassword1!', 'name_en' => 'Sara', 'name_ar' => 'سارة', 'birth_year' => 2016, 'birth_month' => 2]);
        $this->actingAs($parent);
        $page = $this->visit('/family');
    }

    if ($theme === 'dark') {
        $page = $page->inDarkMode();
    }
    $page->resize($width, $height);
    if ($screen === 'enabled') {
        $page->assertSee(__('pages.tf_enabled', [], $locale));
    }
    if ($screen === 'child-reset') {
        $page->click("[commandfor=\"reset-{$child->id}\"]");
    }
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    tfShot($page, "{$screen}-{$locale}-{$theme}-{$width}");
})->with(['setup', 'enabled', 'challenge', 'child-reset'])->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->with(['light', 'dark'])
    ->group('browser', 'T-P1-11', 'A5', 'YG-GAP-A11Y-001');
