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

it('registers, verifies and signs in through the browser forms', function (string $locale, string $name): void {
    $this->withCookie('locale', $locale)->withSession(['locale' => $locale]);
    $page = $this->visit('/register');
    $page->assertSee(__('pages.register_title', [], $locale))
        ->type("#name_{$locale}", $name)->type('#email', 'browser@example.test')
        ->type('#password', 'BrowserPass1!')->type('#password_confirmation', 'BrowserPass1!')
        ->check('#accept_terms')->check('#accept_privacy')
        ->click('main form button[type="submit"]')
        ->waitForText(__('pages.verify_title', [], $locale))
        ->assertPathIs('/email/verify');

    $user = User::where('email', 'browser@example.test')->firstOrFail();
    expect($user->hasRole('student'))->toBeTrue()
        ->and($user->locale)->toBe($locale);

    $link = URL::temporarySignedRoute('verification.verify', now()->addMinute(), ['id' => $user->id, 'hash' => sha1('browser@example.test')]);
    $page = $this->visit($link)->assertPathIs('/my')->assertSee(__('learner.my_enrolled', [], $locale));
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();

    $page->click('#account-menu-trigger, [popovertarget="account-menu"]');
    $page->click('#account-menu form[action$="/logout"] button[type="submit"]')
        ->waitForText(__('pages.login_title', [], $locale))->assertPathIs('/login');

    $page->type('#login', 'browser@example.test')->type('#password', 'BrowserPass1!')
        ->click('main form button[type="submit"]')->waitForText(__('learner.my_enrolled', [], $locale))->assertPathIs('/my')
        ->assertNoJavaScriptErrors();
})->with([['ar', 'طالب تجريبي'], ['en', 'Browser Learner']])->group('browser', 'T-P1-10', 'A1', 'J01');

it('shows field errors in the page when a form is invalid', function (): void {
    $this->withSession(['locale' => 'en']);
    $this->visit('/register')->type('#email', 'not-an-email')->click('main form button[type="submit"]')
        ->waitForText('valid email')->assertPresent('#email[aria-invalid="true"]')->assertNoJavaScriptErrors();
})->group('browser', 'T-P1-10', 'A1', 'A3');

it('lets a parent create a child who then signs in with the username', function (string $locale, string $name): void {
    $this->withCookie('locale', $locale)->withSession(['locale' => $locale]);
    $parent = browserUser('parent', ['locale' => $locale]);
    $this->actingAs($parent);

    $page = $this->visit('/family');
    $page->assertSee(__('pages.family_title', [], $locale))
        ->type("#child_name_{$locale}", $name)->type('#child_username', 'little-browser')
        ->type('#child_birth_year', '2016')->select('#child_birth_month', '4')
        ->type('#child_password', 'ChildBrowser1!')->type('#child_password_confirmation', 'ChildBrowser1!')
        ->click('main form[action$="/guardian/children"] button[type="submit"]')
        ->waitForText($name)->assertSee('little-browser');

    $child = User::where('username', 'little-browser')->firstOrFail();
    expect($child->email)->toBeNull()->and($child->hasRole('student'))->toBeTrue()
        ->and($child->locale)->toBe($locale);

    $page->click('#account-menu-trigger, [popovertarget="account-menu"]');
    $page->click('#account-menu form[action$="/logout"] button[type="submit"]')
        ->waitForText(__('pages.login_title', [], $locale))->assertPathIs('/login');
    $page->type('#login', 'little-browser')->type('#password', 'ChildBrowser1!')
        ->click('main form button[type="submit"]')->waitForText(__('learner.my_enrolled', [], $locale))->assertPathIs('/my')
        ->assertSee($name);
})->with([['ar', 'طفل تجريبي'], ['en', 'Little Browser']])->group('browser', 'T-P1-10', 'A1', 'J02', 'YG-GAP-FAM-001');

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

it('forbids the family page to a parent without child management permission', function (): void {
    $parent = browserUser('parent');
    $parent->roles->sole()->revokePermissionTo('guardian.child.manage');
    $parent->unsetRelation('roles');

    $this->actingAs($parent)->get('/family')->assertForbidden();
})->group('browser', 'T-P1-10', 'A1', 'D-006');

dataset('family-screens', [
    'login' => ['/login', null],
    'register' => ['/register', null],
    'forgot' => ['/forgot-password', null],
    'reset' => ['/reset-password/example-token?email=a%40example.test', null],
    'verify' => ['/email/verify', 'unverified'],
    'profile' => ['/profile', 'student'],
    'family' => ['/family', 'parent'],
    'family-unavailable' => ['/family', 'student'],
    'link' => ['/link', 'student'],
    'not-found' => ['/missing-page-for-screenshot', null],
]);

it('renders every screen accessibly in both directions, themes and sizes', function (string $path, ?string $as, string $locale, int $width, int $height, string $theme) {
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

    $page = $this->visit($path);
    if ($theme === 'dark') {
        $page = $page->inDarkMode();
    }
    $page->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => [document.documentElement.lang, document.documentElement.dir]'))->toBe([$locale, $locale === 'ar' ? 'rtl' : 'ltr']);
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    if ($as === 'parent' && $width === 1440) {
        expect($page->script('() => { const card = document.querySelector("#add-child"); return card.nextElementSibling.getBoundingClientRect().height < card.getBoundingClientRect().height; }'))->toBeTrue();
    }

    $name = trim(str_replace(['/', '?', '=', '%'], '-', explode('?', $path)[0]), '-');
    if ($path === '/family' && $as === 'student') {
        $page->assertPathIs('/family')
            ->assertPresent('[data-family-unavailable] h1')
            ->assertSee(__('pages.family_unavailable_title', [], $locale))
            ->assertVisible('header [popovertarget="account-menu"]')
            ->assertPresent('header nav a[href$="/my/courses"]')
            ->assertPresent('[data-bottom-nav] a[href$="/my/courses"]')
            ->assertNotPresent('#add-child, #link_code, #guardian-verification, form[action*="/guardian/"]');
        $name = 'family-unavailable';
    }

    $directory = base_path('tests/Browser/screenshots/T-P1-10');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P1-10/{$name}-{$locale}-{$theme}-{$width}");
})->with('family-screens')->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->with(['light', 'dark'])
    ->group('browser', 'T-P1-10', 'A2', 'A3', 'YG-GAP-A11Y-001');

it('renders the child-context strip accessibly', function (string $locale, int $width, int $height, string $theme): void {
    $this->withSession(['locale' => $locale]);
    $parent = browserUser('parent');
    $child = app(CreateChildAccount::class)->handle($parent, ['username' => 'strip-kid', 'password' => 'ChildPassword1!', 'name_en' => 'Sara', 'name_ar' => 'سارة', 'birth_year' => 2016, 'birth_month' => 2]);
    $this->actingAs($parent)->withSession(['acting_child_id' => $child->id, 'locale' => $locale]);

    $page = $this->visit('/family');
    if ($theme === 'dark') {
        $page = $page->inDarkMode();
    }
    $page->resize($width, $height);
    $page->assertPresent('[data-child-context]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    $directory = base_path('tests/Browser/screenshots/T-P1-10');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $page->screenshot(fullPage: true, filename: "T-P1-10/child-context-{$locale}-{$theme}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->with(['light', 'dark'])
    ->group('browser', 'T-P1-10', 'A2', 'YG-GAP-FAM-004');

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
