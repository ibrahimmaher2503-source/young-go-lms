<?php

declare(strict_types=1);

use App\Domain\Identity\TwoFactorSession;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['identity.two_factor_enforced' => true]);
});

function tfUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(['password' => 'Password1!secure', ...$attributes]);
    $user->assignRole($role);

    return $user;
}

function tfAs($test, User $user)
{
    $test->flushSession();

    return $test->actingAs($user);
}

/** The TOTP library reads the real clock, so rewind the stored step instead of travelling in time. */
function tfRewind(User $user): void
{
    $user->forceFill(['two_factor_last_step' => (int) $user->refresh()->two_factor_last_step - 5])->save();
}

/** Enrolls through the real endpoints and returns [secret, recovery codes]. */
function tfEnroll($test, User $user): array
{
    tfAs($test, $user)->get('/security/two-factor')->assertOk();
    $secret = (string) $user->refresh()->two_factor_secret;
    $codes = $test->postJson('/security/two-factor/confirm', ['code' => app(Google2FA::class)->getCurrentOtp($secret), 'password' => 'Password1!secure'])
        ->assertOk()->json('recovery_codes');

    return [$secret, $codes];
}

it('keeps an admin without 2FA out of the panel and sends them to setup', function (string $role, string $panel): void {
    tfAs($this, tfUser($role))->get("/{$panel}")->assertRedirect('/security/two-factor');
    $this->getJson("/{$panel}")->assertForbidden()->assertJsonPath('two_factor', 'setup');
})->with([['super_admin', 'admin'], ['admin', 'admin'], ['finance', 'finance']])->group('T-P1-11', 'A1', 'YG-GAP-SEC-003');

it('does not require 2FA of optional roles', function (): void {
    tfAs($this, tfUser('instructor_academy'))->get('/instructor')->assertOk();
    $this->get('/profile')->assertOk();
})->group('T-P1-11', 'A1', 'ED-016');

it('enrolls with a valid code and gives eight single-use recovery codes', function (): void {
    $admin = tfUser('admin');
    tfAs($this, $admin)->get('/security/two-factor')->assertOk()->assertSee('<svg', false)->assertSee('data-two-factor-secret', false);
    $secret = (string) $admin->refresh()->two_factor_secret;

    $current = app(Google2FA::class)->getCurrentOtp($secret);
    $this->postJson('/security/two-factor/confirm', ['code' => '000000', 'password' => 'Password1!secure'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson('/security/two-factor/confirm', ['code' => $current, 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect($admin->refresh()->hasTwoFactorEnabled())->toBeFalse();
    $codes = $this->postJson('/security/two-factor/confirm', ['code' => $current, 'password' => 'Password1!secure'])
        ->assertOk()->assertJsonCount(8, 'recovery_codes')->json('recovery_codes');

    expect($codes)->each->toMatch('/^[0-9a-f]{5}-[0-9a-f]{5}$/')
        ->and($admin->refresh()->hasTwoFactorEnabled())->toBeTrue();
    $this->get('/admin')->assertOk();
    expect(Activity::query()->where('event', 'auth.two_factor_enabled')->sole()->causer_id)->toBe($admin->id);
})->group('T-P1-11', 'A1', 'YG-GAP-SEC-003');

it('stores the secret encrypted and the recovery codes only as hashes', function (): void {
    $admin = tfUser('admin');
    [$secret, $codes] = tfEnroll($this, $admin);

    $raw = DB::table('users')->where('id', $admin->id)->first();
    expect($raw->two_factor_secret)->not->toContain($secret)
        ->and($raw->two_factor_recovery_codes)->not->toContain($codes[0])
        ->and($admin->refresh()->toArray())->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes']);
})->group('T-P1-11', 'A1', 'YG-GAP-SEC-003');

it('challenges an enrolled user in each new session before any protected page', function (): void {
    $admin = tfUser('admin');
    [$secret] = tfEnroll($this, $admin);

    tfAs($this, $admin->refresh())->get('/admin')->assertRedirect('/two-factor-challenge');
    $this->get('/two-factor-challenge')->assertOk()->assertSee(__('pages.tf_challenge_title'));
    $this->get('/security/two-factor')->assertRedirect('/two-factor-challenge');

    tfRewind($admin);
    $this->postJson('/two-factor-challenge', ['code' => '123456'])->assertUnprocessable();
    $this->postJson('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertNoContent();
    $this->get('/admin')->assertOk();
})->group('T-P1-11', 'A1', 'YG-GAP-SEC-003');

it('rejects a reused authenticator code', function (): void {
    $admin = tfUser('admin');
    [$secret] = tfEnroll($this, $admin);
    tfRewind($admin);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    tfAs($this, $admin->refresh())->postJson('/two-factor-challenge', ['code' => $code])->assertNoContent();
    tfAs($this, $admin->refresh())->postJson('/two-factor-challenge', ['code' => $code])->assertUnprocessable();
})->group('T-P1-11', 'A1');

it('accepts each recovery code once and audits its use', function (): void {
    $admin = tfUser('admin');
    [, $codes] = tfEnroll($this, $admin);

    tfAs($this, $admin->refresh())->postJson('/two-factor-challenge', ['code' => strtoupper($codes[0])])->assertNoContent();
    tfAs($this, $admin->refresh())->postJson('/two-factor-challenge', ['code' => $codes[0]])->assertUnprocessable();
    expect(Activity::query()->where('event', 'auth.two_factor_recovery_used')->sole()->properties->get('after'))->toBe(['remaining' => 7])
        ->and($admin->refresh()->two_factor_recovery_codes)->toHaveCount(7);
})->group('T-P1-11', 'A1');

it('throttles repeated wrong challenge codes', function (): void {
    $admin = tfUser('admin');
    tfEnroll($this, $admin);
    tfAs($this, $admin->refresh());

    $statuses = collect(range(1, 6))->map(fn (): int => $this->postJson('/two-factor-challenge', ['code' => '111111'])->status());

    expect($statuses->first())->toBe(422)->and($statuses->last())->toBe(429);
})->group('T-P1-11', 'A1');

it('does not let a second user inherit another user’s passed challenge', function (): void {
    $first = tfUser('admin');
    tfEnroll($this, $first);
    $second = tfUser('admin');
    tfEnroll($this, $second);

    $this->flushSession();
    $this->withSession([TwoFactorSession::KEY => $second->id])->actingAs($first->refresh())
        ->get('/profile')->assertRedirect('/two-factor-challenge');
})->group('T-P1-11', 'A1');

it('lets optional roles turn 2FA on and off but not required roles', function (): void {
    $parent = tfUser('parent');
    tfEnroll($this, $parent);
    tfAs($this, $parent->refresh())->get('/family')->assertRedirect('/two-factor-challenge');

    $admin = tfUser('admin');
    tfEnroll($this, $admin);
    $this->deleteJson('/security/two-factor', ['password' => 'Password1!secure'])->assertForbidden();
    expect($admin->refresh()->hasTwoFactorEnabled())->toBeTrue();

    $parent2 = tfUser('parent');
    tfEnroll($this, $parent2);
    $this->deleteJson('/security/two-factor', ['password' => 'wrong'])->assertUnprocessable();
    $this->deleteJson('/security/two-factor', ['password' => 'Password1!secure'])->assertNoContent();
    expect($parent2->refresh()->hasTwoFactorEnabled())->toBeFalse()->and($parent2->two_factor_secret)->toBeNull();
    expect(Activity::query()->where('event', 'auth.two_factor_disabled')->sole()->causer_id)->toBe($parent2->id);
})->group('T-P1-11', 'A1', 'ED-016');

it('regenerates recovery codes only with the password', function (): void {
    $admin = tfUser('admin');
    [, $old] = tfEnroll($this, $admin);

    $this->postJson('/security/two-factor/recovery-codes', ['password' => 'nope'])->assertUnprocessable();
    $new = $this->postJson('/security/two-factor/recovery-codes', ['password' => 'Password1!secure'])->assertOk()->json('recovery_codes');
    expect($new)->toHaveCount(8)->and($new)->not->toContain($old[0]);
})->group('T-P1-11', 'A1');

it('lets an operator reset 2FA from the command line with an audit entry', function (): void {
    $admin = tfUser('admin', ['email' => 'ops@example.test']);
    tfEnroll($this, $admin);

    $this->artisan('app:two-factor-reset', ['email' => 'OPS@example.test'])->assertSuccessful();
    expect($admin->refresh()->hasTwoFactorEnabled())->toBeFalse();
    $entry = Activity::query()->where('event', 'auth.two_factor_disabled')->sole();
    expect($entry->causer_id)->toBeNull()->and($entry->properties->get('context'))->toBe(['via' => 'cli']);
})->group('T-P1-11', 'A1');

it('applies a per-user cap that a changing address cannot sidestep', function (): void {
    $admin = tfUser('admin');
    tfEnroll($this, $admin);
    tfAs($this, $admin->refresh());

    $statuses = collect(range(1, 14))->map(fn (int $i): int => $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$i])->postJson('/two-factor-challenge', ['code' => '111111'])->status());

    expect($statuses->first())->toBe(422)->and($statuses->last())->toBe(429);
})->group('T-P1-11', 'A1', 'YG-GAP-SEC-003');

it('forgets a passed challenge when someone signs in again in the same session', function (): void {
    $user = tfUser('student', ['email' => 'again@example.test']);
    $this->withSession([TwoFactorSession::KEY => $user->id]);

    $this->postJson('/login', ['login' => 'again@example.test', 'password' => 'Password1!secure'])->assertNoContent();
    expect(session(TwoFactorSession::KEY))->toBeNull();
})->group('T-P1-11', 'A1');

it('does not cache the setup page that carries the secret', function (): void {
    tfAs($this, tfUser('admin'))->get('/security/two-factor')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
})->group('T-P1-11', 'A1');
