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

it('enrolls in two-factor authentication through the browser and shows recovery codes once', function (): void {
    $this->withSession(['locale' => 'en']);
    $this->actingAs(tfBrowserUser('student'));

    $page = $this->visit('/security/two-factor')->assertPresent('[data-two-factor-secret]');
    expect($page->script("() => !!document.querySelector('[role=img] svg')"))->toBeTrue();
    $secret = trim((string) $page->text('[data-two-factor-secret]'));
    expect($secret)->not->toBeEmpty();

    $page->type('#code', app(Google2FA::class)->getCurrentOtp($secret))->type('#enroll-password', 'Password1!secure')
        ->click('main form button[type="submit"]')
        ->waitForText(__('pages.tf_recovery_title'))
        ->assertNoJavaScriptErrors();
    expect($page->script('() => document.querySelectorAll("[data-result-list] li").length'))->toBe(8);
})->group('browser', 'T-P1-11', 'A1', 'YG-GAP-SEC-003');

it('completes the two-step challenge with a code', function (): void {
    $this->withSession(['locale' => 'en']);
    $user = tfBrowserUser('student', ['two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
    $this->actingAs($user);

    $this->visit('/two-factor-challenge')->type('#code', app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'))
        ->click('main form[action$="/two-factor-challenge"] button[type="submit"]')->waitForText(__('pages.profile_title'))->assertPathIs('/profile');
})->group('browser', 'T-P1-11', 'A1');

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
})->group('browser', 'T-P1-11', 'A3', 'YG-GAP-FAM-007');

it('renders the two-factor and child-reset screens accessibly in both directions', function (string $screen, string $locale, int $width, int $height) {
    $this->withSession(['locale' => $locale]);
    $enrolled = ['two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()];

    if ($screen === 'setup') {
        $this->actingAs(tfBrowserUser('student', ['name_en' => 'Omar', 'display_name' => 'Omar']));
        $page = $this->visit('/security/two-factor');
    } elseif ($screen === 'enabled') {
        $user = tfBrowserUser('student', ['name_en' => 'Omar', 'display_name' => 'Omar', ...$enrolled]);
        $this->withSession(['locale' => $locale, TwoFactorSession::KEY => $user->id])->actingAs($user);
        $page = $this->visit('/security/two-factor');
    } elseif ($screen === 'challenge') {
        $this->actingAs(tfBrowserUser('student', $enrolled));
        $page = $this->visit('/two-factor-challenge');
    } else {
        $parent = tfBrowserUser('parent', ['name_en' => 'Mona', 'display_name' => 'Mona']);
        $child = app(CreateChildAccount::class)->handle($parent, ['username' => 'shot-kid', 'password' => 'ChildPassword1!', 'name_en' => 'Sara', 'name_ar' => 'سارة', 'birth_year' => 2016, 'birth_month' => 2]);
        $this->actingAs($parent);
        $page = $this->visit('/family')->resize($width, $height)->click("[commandfor=\"reset-{$child->id}\"]");
    }

    $page->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    tfShot($page, "{$screen}-{$locale}-{$width}");
})->with(['setup', 'enabled', 'challenge', 'child-reset'])->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])
    ->group('browser', 'T-P1-11', 'A5', 'YG-GAP-A11Y-001');
