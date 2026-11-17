<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateChildAccount;
use App\Domain\Identity\Actions\RedeemLinkCode;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['identity.terms_version' => 'test-terms', 'identity.privacy_version' => 'test-privacy']);
});

function browserUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['password' => 'Password1!secure', ...$attributes]);
    $user->assignRole($role);

    return $user;
}

it('registers, verifies and signs in through the browser forms', function (): void {
    $this->withSession(['locale' => 'en']);
    $page = $this->visit('/register');
    $page->type('#name_en', 'Browser Learner')->type('#email', 'browser@example.test')
        ->type('#password', 'BrowserPass1!')->type('#password_confirmation', 'BrowserPass1!')
        ->check('#accept_terms')->check('#accept_privacy')
        ->click('main form button[type="submit"]')
        ->waitForText(__('pages.verify_title'))
        ->assertPathIs('/email/verify');

    $user = User::where('email', 'browser@example.test')->firstOrFail();
    expect($user->hasRole('student'))->toBeTrue();

    $link = URL::temporarySignedRoute('verification.verify', now()->addMinute(), ['id' => $user->id, 'hash' => sha1('browser@example.test')]);
    $page = $this->visit($link)->assertPathIs('/profile');
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();

    $page->click('#account-menu-trigger, [popovertarget="account-menu"]');
    $page->click('#account-menu button[type="submit"]')->waitForText(__('pages.login_title'))->assertPathIs('/login');

    $page->type('#login', 'browser@example.test')->type('#password', 'BrowserPass1!')
        ->click('main form button[type="submit"]')->waitForText(__('pages.profile_title'))->assertPathIs('/profile')
        ->assertNoJavaScriptErrors();
})->group('browser', 'T-P1-10', 'A1', 'J01');

it('shows field errors in the page when a form is invalid', function (): void {
    $this->withSession(['locale' => 'en']);
    $this->visit('/register')->type('#email', 'not-an-email')->click('main form button[type="submit"]')
        ->waitForText('valid email')->assertPresent('#email[aria-invalid="true"]')->assertNoJavaScriptErrors();
})->group('browser', 'T-P1-10', 'A1', 'A3');

it('lets a parent create a child who then signs in with the username', function (): void {
    $this->withSession(['locale' => 'en']);
    $parent = browserUser('parent');
    $this->actingAs($parent);

    $page = $this->visit('/family');
    $page->type('#child_name_en', 'Little Browser')->type('#child_username', 'little-browser')
        ->type('#child_birth_year', '2016')->select('#child_birth_month', '4')
        ->type('#child_password', 'ChildBrowser1!')->type('#child_password_confirmation', 'ChildBrowser1!')
        ->click('main form[action$="/guardian/children"] button[type="submit"]')
        ->waitForText('Little Browser')->assertSee('little-browser');

    $child = User::where('username', 'little-browser')->firstOrFail();
    expect($child->email)->toBeNull()->and($child->hasRole('student'))->toBeTrue();

    $page->click('#account-menu-trigger, [popovertarget="account-menu"]');
    $page->click('#account-menu button[type="submit"]')->waitForText(__('pages.login_title'));
    $page->type('#login', 'little-browser')->type('#password', 'ChildBrowser1!')
        ->click('main form button[type="submit"]')->waitForText(__('pages.profile_title'))->assertPathIs('/profile');
})->group('browser', 'T-P1-10', 'A1', 'J02', 'YG-GAP-FAM-001');

it('switches into a child context with the password and exits again', function (): void {
    $this->withSession(['locale' => 'en']);
    $parent = browserUser('parent');
    $child = app(CreateChildAccount::class)->handle($parent, [
        'username' => 'switch-kid', 'password' => 'ChildPassword1!', 'name_en' => 'Switch Kid', 'birth_year' => 2016, 'birth_month' => 3,
    ]);
    $this->actingAs($parent);

    $page = $this->visit('/family');
    $page->click("[commandfor=\"switch-{$child->id}\"]")
        ->type("#switch-password-{$child->id}", 'wrong-password')->click("#switch-{$child->id} form[data-ajax] button[type=\"submit\"]")
        ->waitForText('incorrect')->assertNotPresent('[data-child-context]');
    $page->type("#switch-password-{$child->id}", '')->fill("#switch-password-{$child->id}", 'Password1!secure')
        ->click("#switch-{$child->id} form[data-ajax] button[type=\"submit\"]")
        ->waitForText(__('pages.child_context', ['name' => 'Switch Kid']))->assertPresent('[data-child-context]');
    $page->click('[data-child-context] button[type="submit"]')->wait(1)->assertNotPresent('[data-child-context]');
})->group('browser', 'T-P1-10', 'A1', 'YG-GAP-FAM-004');

it('links an existing learner with a code the learner confirms', function (): void {
    $this->withSession(['locale' => 'en']);
    $learner = browserUser('student', ['display_name' => 'Linking Learner', 'name' => 'Linking Learner']);
    $parent = browserUser('parent', ['display_name' => 'Linking Parent', 'name' => 'Linking Parent']);

    $this->actingAs($learner);
    $code = $this->visit('/link')->click('main form button[type="submit"]')->waitForText(__('pages.code_result'));
    $issued = (string) $code->text('#issued-code [data-result-value]');
    expect($issued)->toHaveLength(8);

    app(RedeemLinkCode::class)->handle($parent, $issued);
    $this->visit('/link')->assertSee('Linking Parent')->click('main form[action*="/confirm"] button[type="submit"]')
        ->waitForText(__('pages.no_pending'));
    expect(GuardianChildLink::query()->sole()->status)->toBe('verified');
})->group('browser', 'T-P1-10', 'A1', 'YG-GAP-FAM-002');

dataset('family-screens', [
    'login' => ['/login', null],
    'register' => ['/register', null],
    'forgot' => ['/forgot-password', null],
    'reset' => ['/reset-password/example-token?email=a%40example.test', null],
    'verify' => ['/email/verify', 'unverified'],
    'profile' => ['/profile', 'student'],
    'family' => ['/family', 'parent'],
    'link' => ['/link', 'student'],
    'not-found' => ['/missing-page-for-screenshot', null],
]);

it('renders every screen accessibly in both directions and sizes', function (string $path, ?string $as, string $locale, int $width, int $height) {
    $this->withSession(['locale' => $locale]);
    if ($as === 'parent') {
        $parent = browserUser('parent', ['name_en' => 'Mona', 'display_name' => 'Mona']);
        app(CreateChildAccount::class)->handle($parent, ['username' => 'shot-kid', 'password' => 'ChildPassword1!', 'name_en' => 'Sara', 'name_ar' => 'سارة', 'birth_year' => 2016, 'birth_month' => 2]);
        $this->actingAs($parent);
    } elseif ($as === 'student') {
        $this->actingAs(browserUser('student', ['name_en' => 'Omar', 'display_name' => 'Omar']));
    } elseif ($as === 'unverified') {
        $this->actingAs(browserUser('student', ['email' => 'unverified@example.test', 'email_verified_at' => null]));
    }

    $page = $this->visit($path)->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => [document.documentElement.lang, document.documentElement.dir]'))->toBe([$locale, $locale === 'ar' ? 'rtl' : 'ltr']);
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $name = trim(str_replace(['/', '?', '=', '%'], '-', explode('?', $path)[0]), '-');
    $directory = base_path('tests/Browser/screenshots/T-P1-10');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P1-10/{$name}-{$locale}-{$width}");
})->with('family-screens')->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])
    ->group('browser', 'T-P1-10', 'A2', 'A3', 'YG-GAP-A11Y-001');

it('renders the child-context strip accessibly', function (string $locale): void {
    $this->withSession(['locale' => $locale]);
    $parent = browserUser('parent');
    $child = app(CreateChildAccount::class)->handle($parent, ['username' => 'strip-kid', 'password' => 'ChildPassword1!', 'name_en' => 'Sara', 'name_ar' => 'سارة', 'birth_year' => 2016, 'birth_month' => 2]);
    $this->actingAs($parent)->withSession(['acting_child_id' => $child->id, 'locale' => $locale]);

    $page = $this->visit('/family')->resize(390, 844);
    $page->assertPresent('[data-child-context]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    $directory = base_path('tests/Browser/screenshots/T-P1-10');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P1-10/child-context-{$locale}-390");
})->with(['ar', 'en'])->group('browser', 'T-P1-10', 'A2', 'YG-GAP-FAM-004');

it('renders the main screens in dark mode', function (string $path, string $locale): void {
    $this->withSession(['locale' => $locale]);
    $page = $this->visit($path)->inDarkMode()->resize(390, 844);
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBeTrue();
    $name = trim($path, '/');
    $directory = base_path('tests/Browser/screenshots/T-P1-10');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P1-10/{$name}-{$locale}-dark-390");
})->with(['/login', '/register'])->with(['ar', 'en'])->group('browser', 'T-P1-10', 'A2', 'A3');

it('shows live password strength and a copyable sign-in detail on the family page', function (): void {
    $this->withSession(['locale' => 'en']);
    $parent = browserUser('parent');
    app(CreateChildAccount::class)->handle($parent, ['username' => 'copy-kid', 'password' => 'ChildPassword1!', 'name_en' => 'Copy Kid', 'birth_year' => 2016, 'birth_month' => 3]);
    $this->actingAs($parent);

    $page = $this->visit('/family');
    $page->assertPresent('[data-copy-text]')->assertSee(__('pages.copy_login'));
    expect($page->attribute('[data-copy-text]', 'data-copy-text'))->toContain('copy-kid')->not->toContain('ChildPassword1!');

    $page->type('#child_password', 'abc');
    expect($page->script("() => document.querySelector('[data-strength-meter] [data-strength-text]').textContent"))->toBe(__('pages.strength_weak'));
    $page->fill('#child_password', 'LongEnough1!xyz');
    expect($page->script("() => document.querySelector('[data-strength-meter] [data-strength-text]').textContent"))->toBe(__('pages.strength_strong'));
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
})->group('browser', 'T-P1-10', 'A1', 'J02');

it('offers back and retry actions on error pages', function (): void {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_error/429', static fn () => abort(429));
    $this->withSession(['locale' => 'en']);

    $this->visit('/_error/429')->assertSee(__('errors.go_back'))->assertSee(__('errors.retry'))->assertNoAccessibilityIssues();
})->group('browser', 'T-P1-10', 'A2', 'YG-GAP-OPS-004');
