<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    config(['identity.terms_version' => 'test-terms', 'identity.privacy_version' => 'test-privacy']);
});

function identityRegistration(array $overrides = []): array
{
    return [...[
        'email' => 'new@example.test',
        'username' => 'new-account',
        'password' => 'ExamplePassword1!',
        'password_confirmation' => 'ExamplePassword1!',
        'name_en' => 'Test Learner',
        'birth_year' => 2000,
        'birth_month' => 6,
        'terms_version' => 'test-terms',
        'privacy_version' => 'test-privacy',
        'accept_terms' => true,
        'accept_privacy' => true,
    ], ...$overrides];
}

it('rejects duplicate identities using the selected locale', function () {
    User::factory()->create(['email' => 'taken@example.test', 'username' => 'taken']);
    $this->withSession(['locale' => 'ar'])
        ->postJson('/register', identityRegistration(['email' => 'TAKEN@example.test', 'username' => 'TAKEN']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', trans('validation.unique', ['attribute' => trans('validation.attributes.email', [], 'ar')], 'ar'))
        ->assertJsonPath('errors.username.0', trans('validation.unique', ['attribute' => trans('validation.attributes.username', [], 'ar')], 'ar'));
})->group('T-P1-02', 'A1', 'YG-GAP-ID-001');

it('records only birth year and month and current consent versions', function () {
    $this->postJson('/register', identityRegistration(['birth_date' => '2000-06-28']))->assertCreated();
    $user = User::where('email', 'new@example.test')->firstOrFail();
    expect($user->birth_year)->toBe(2000)->and($user->birth_month)->toBe(6);
    expect(Schema::hasColumn('users', 'birth_date'))->toBeFalse();
    expect(DB::table('consents')->where('user_id', $user->id)->pluck('version', 'type')->all())->toBe([
        'terms' => 'test-terms',
        'privacy' => 'test-privacy',
    ]);
    Notification::assertSentTo($user, VerifyEmail::class);
})->group('T-P1-02', 'A4', 'YG-GAP-FAM-003', 'YG-GAP-ID-002');

it('requires acceptance of the configured versions and fails closed when missing', function () {
    $this->postJson('/register', identityRegistration(['privacy_version' => 'stale']))->assertUnprocessable();
    config(['identity.privacy_version' => null]);
    $this->postJson('/register', identityRegistration())->assertServiceUnavailable();
    expect(User::count())->toBe(0);
})->group('T-P1-02', 'YG-GAP-ID-002');

it('blocks inactive signed-in accounts on protected routes', function (string $status) {
    $user = User::factory()->create(['status' => $status]);
    $this->actingAs($user)->postJson('/email/verification-notification')->assertForbidden();
    $this->assertGuest();
})->with(['suspended', 'deactivated'])->group('T-P1-02', 'A2');

it('rate limits login using the configured limit', function () {
    config(['identity.login_max_attempts' => 2]);
    for ($i = 0; $i < 2; $i++) {
        $this->postJson('/login', ['login' => 'test@example.test', 'password' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson('/login', ['login' => 'test@example.test', 'password' => 'wrong'])->assertTooManyRequests();
})->group('T-P1-02', 'A3');

it('authenticates by email or username and invalidates the session on logout', function (string $login) {
    $user = User::factory()->create(['email' => 'login@example.test', 'username' => 'test-user']);
    $this->postJson('/login', ['login' => $login, 'password' => 'password'])->assertNoContent();
    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
    $this->postJson('/logout')->assertNoContent();
    $this->assertGuest();
})->with(['LOGIN@example.test', 'TEST-USER'])->group('T-P1-02');

it('verifies a signed email link for the current account', function () {
    $user = User::factory()->unverified()->create();
    $link = URL::temporarySignedRoute('verification.verify', now()->addMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->actingAs($user)->getJson($link)->assertNoContent();
    expect($user->refresh()->email_verified_at)->not->toBeNull();
})->group('T-P1-02');

it('sends a reset link with a generic response and only consumes its token once', function () {
    $user = User::factory()->create();
    $this->postJson('/forgot-password', ['email' => $user->email])->assertNoContent();
    $this->postJson('/forgot-password', ['email' => 'missing@example.test'])->assertNoContent();
    $token = Notification::sent($user, ResetPassword::class)->sole()->token;
    $payload = ['email' => $user->email, 'token' => $token, 'password' => 'ChangedPassword1!', 'password_confirmation' => 'ChangedPassword1!'];
    $this->postJson('/reset-password', $payload)->assertNoContent();
    $this->postJson('/reset-password', $payload)->assertUnprocessable();
    $this->postJson('/login', ['login' => $user->email, 'password' => 'ChangedPassword1!'])->assertNoContent();
})->group('T-P1-02');

it('invalidates a signed-in session after a password changes before a protected request', function () {
    $user = User::factory()->create();
    $this->postJson('/login', ['login' => $user->email, 'password' => 'password'])
        ->assertNoContent()
        ->assertSessionHas('password_hash_web');

    $user->forceFill(['password' => 'NewExamplePassword1!'])->save();
    Auth::forgetGuards();
    $this->postJson('/email/verification-notification')->assertUnauthorized();
    $this->assertGuest();
})->group('T-P1-02');

it('never sends authentication secrets to configured log mailers', function (string $mailer) {
    config(['mail.default' => $mailer, 'mail.mailers.failover.mailers' => ['smtp', 'log']]);
    $this->postJson('/register', identityRegistration())->assertCreated();
    $user = User::where('email', 'new@example.test')->firstOrFail();
    $this->postJson('/email/verification-notification')->assertNoContent();
    $this->postJson('/forgot-password', ['email' => $user->email])->assertNoContent();

    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
    expect(DB::table('consents')->where('user_id', $user->id)->count())->toBe(2);
})->with(['log', 'failover'])->group('T-P1-02', 'YG-GAP-ID-002');
