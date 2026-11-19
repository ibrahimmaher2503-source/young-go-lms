<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateChildAccount;
use App\Domain\Identity\Actions\IssueLinkCode;
use App\Domain\Identity\Actions\RedeemLinkCode;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['identity.terms_version' => 'test-terms', 'identity.privacy_version' => 'test-privacy']);
});

function pageUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['password' => 'Password1!secure', ...$attributes]);
    $user->assignRole($role);

    return $user;
}

function asUser($test, User $user)
{
    $test->flushSession();

    return $test->actingAs($user);
}

function pageChild(User $parent, string $username = 'page-child'): User
{
    return app(CreateChildAccount::class)->handle($parent, [
        'username' => $username, 'password' => 'ChildPassword1!', 'name_en' => 'Little Ali', 'name_ar' => 'علي الصغير', 'birth_year' => 2016, 'birth_month' => 3,
    ]);
}

it('renders the auth screens for guests in both locales', function (string $path, string $locale, string $marker): void {
    $this->withSession(['locale' => $locale])->get($path)->assertOk()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee($locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"', false)
        ->assertSee($marker);
})->with([
    ['/login', 'en', 'Email or username'], ['/login', 'ar', 'البريد الإلكتروني أو اسم المستخدم'],
    ['/register', 'en', 'Create account'], ['/register', 'ar', 'إنشاء الحساب'],
    ['/forgot-password', 'en', 'Send reset link'], ['/forgot-password', 'ar', 'إرسال الرابط'],
    ['/reset-password/abc?email=a%40example.test', 'en', 'Save new password'],
])->group('T-P1-10', 'A3', 'YG-GAP-UX-001');

it('shows an honest notice instead of the form when legal versions are not configured', function (): void {
    config(['identity.terms_version' => null]);

    $this->get('/register')->assertOk()->assertSee(__('auth.registration_unavailable'))->assertDontSee('name="password"', false);
})->group('T-P1-10', 'A1');

it('sends signed-in users away from guest screens and routes them by role', function (): void {
    asUser($this, pageUser('parent'))->get('/login')->assertRedirect('/home');
    asUser($this, pageUser('parent'))->get('/home')->assertRedirect('/parent');
    asUser($this, pageUser('student'))->get('/home')->assertRedirect('/my');
    asUser($this, pageUser('admin'))->get('/home')->assertRedirect('/admin');
})->group('T-P1-10', 'A1');

it('redirects guests to sign in from account pages', function (string $path): void {
    $this->get($path)->assertRedirect('/login');
})->with(['/home', '/profile', '/family', '/link', '/email/verify'])->group('T-P1-10', 'A1');

it('lists only the signed-in parent’s linked children with a switcher', function (): void {
    $parent = pageUser('parent');
    $other = pageUser('parent');
    pageChild($parent);
    pageChild($other, 'other-child');

    asUser($this, $parent)->get('/family')->assertOk()
        ->assertSee('علي الصغير')->assertSee('page-child')->assertDontSee('other-child')
        ->assertSee(__('pages.switch_child'))->assertSee('/guardian/context/', false);
})->group('T-P1-10', 'A1', 'J02');

it('limits the family and link screens to their roles', function (): void {
    asUser($this, pageUser('student'))->get('/family')->assertOk()->assertSee('data-family-unavailable', false);
    asUser($this, pageUser('parent'))->get('/link')->assertForbidden();
    asUser($this, pageUser('super_admin'))->get('/family')->assertForbidden();
})->group('T-P1-10', 'A1', 'D-006');

it('lets a parent create a child through the form endpoint and the child sign in by username', function (): void {
    $parent = pageUser('parent');
    $payload = [
        'username' => 'New-Kid', 'password' => 'ChildPassword1!', 'password_confirmation' => 'ChildPassword1!',
        'name_en' => 'New Kid', 'birth_year' => 2017, 'birth_month' => 5,
    ];

    asUser($this, $parent)->postJson('/guardian/children', $payload)->assertCreated();
    $this->postJson('/guardian/children', [...$payload, 'username' => 'bad name!'])->assertUnprocessable()->assertJsonValidationErrors('username');
    $this->postJson('/guardian/children', [...$payload, 'username' => 'NEW-kid'])->assertUnprocessable()->assertJsonValidationErrors('username');
    $this->flushSession();
    Auth::logout();
    $this->postJson('/login', ['login' => 'new-kid', 'password' => 'ChildPassword1!'])->assertNoContent();
    asUser($this, pageUser('student'))->postJson('/guardian/children', $payload)->assertForbidden();
})->group('T-P1-10', 'A1', 'J02', 'YG-GAP-FAM-001');

it('shows the learner pending requests and the parent pending links', function (): void {
    $learner = pageUser('student', ['name' => 'Sara Learner', 'display_name' => 'Sara Learner']);
    $parent = pageUser('parent', ['name' => 'Mona Parent', 'display_name' => 'Mona Parent']);
    app(RedeemLinkCode::class)->handle($parent, app(IssueLinkCode::class)->handle($learner));

    asUser($this, $learner)->get('/link')->assertOk()
        ->assertSee(__('pages.linked_by', ['name' => 'Mona Parent']))->assertSee(__('pages.confirm_link'));
    asUser($this, $parent)->get('/family')->assertOk()->assertSee('Sara Learner')->assertSee(__('pages.pending_state'));
})->group('T-P1-10', 'A1', 'YG-GAP-FAM-002');

it('shows the child-context strip with an exit control only while acting as a child', function (): void {
    $parent = pageUser('parent');
    $child = pageChild($parent);

    asUser($this, $parent)->get('/family')->assertOk()->assertDontSee('data-child-context', false);
    $this->withSession(['acting_child_id' => $child->id])->get('/family')->assertOk()
        ->assertSee('data-child-context', false)
        ->assertSee(__('pages.child_context', ['name' => 'علي الصغير']))->assertSee(__('pages.exit_child_context'));
})->group('T-P1-10', 'A1', 'YG-GAP-FAM-004');

it('renders the profile screen with each section', function (): void {
    asUser($this, pageUser('student', ['name_en' => 'Omar']))->get('/profile')->assertOk()
        ->assertSee(__('pages.profile_names'))->assertSee(__('pages.certificate_names'))
        ->assertSee(__('pages.avatar'))->assertSee(__('pages.change_password'))->assertSee('value="Omar"', false);
})->group('T-P1-10', 'A1');

it('shows the verification notice only to unverified accounts with an email', function (): void {
    asUser($this, pageUser('student', ['email_verified_at' => null, 'email' => 'v@example.test']))
        ->get('/email/verify')->assertOk()->assertSee('v@example.test');
    asUser($this, pageUser('student'))->get('/email/verify')->assertRedirect('/home');
})->group('T-P1-10', 'A1');

it('redirects browsers to home after email verification but keeps the API contract', function (): void {
    $user = pageUser('student', ['email_verified_at' => null, 'email' => 'verify@example.test']);
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinute(), ['id' => $user->id, 'hash' => sha1('verify@example.test')]);

    asUser($this, $user)->withHeader('Accept', 'text/html')->get($url)->assertRedirect('/home');
})->group('T-P1-10', 'A1');

it('returns the user to the current page after switching language', function (): void {
    $this->post('/locale', ['locale' => 'en', 'return_to' => '/login'])->assertRedirect('/login');
    $this->post('/locale', ['locale' => 'en', 'return_to' => '//evil.example'])->assertSessionHasErrors('return_to');
})->group('T-P1-10', 'A1');

it('renders branded translated error pages', function (int $status, string $locale): void {
    app()->setLocale($locale);
    $view = view('errors.'.$status)->render();

    expect($view)->toContain(__('errors.'.$status.'_title', [], $locale))
        ->and($view)->toContain($locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"');
})->with(function () {
    foreach ([403, 404, 419, 429, 500, 503] as $status) {
        yield "{$status} en" => [$status, 'en'];
        yield "{$status} ar" => [$status, 'ar'];
    }
})->group('T-P1-10', 'A1', 'YG-GAP-OPS-004');

it('serves the 404 page for unknown routes and 403 for denied pages', function (): void {
    $this->get('/definitely-missing')->assertNotFound()->assertSee(__('errors.404_title'));
    asUser($this, pageUser('super_admin'))->get('/family')->assertForbidden()->assertSee(__('errors.403_title'));
})->group('T-P1-10', 'A1', 'YG-GAP-OPS-004');

it('audits child creation through the page flow', function (): void {
    $parent = pageUser('parent');
    asUser($this, $parent)->postJson('/guardian/children', [
        'username' => 'audited-kid', 'password' => 'ChildPassword1!', 'password_confirmation' => 'ChildPassword1!',
        'name_en' => 'Kid', 'birth_year' => 2016, 'birth_month' => 1,
    ])->assertCreated();

    expect(Activity::query()->where('event', 'guardian.child_created')->sole()->causer_id)->toBe($parent->id);
})->group('T-P1-10', 'A1', 'PRD-22');
