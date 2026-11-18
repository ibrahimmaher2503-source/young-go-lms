<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateChildAccount;
use App\Domain\Identity\Actions\EnterChildContext;
use App\Domain\Identity\Actions\IssueLinkCode;
use App\Domain\Identity\Actions\RedeemLinkCode;
use App\Domain\Identity\LinkCodeHasher;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Identity\Models\UserDevice;
use App\Domain\Identity\Models\UserSession;
use App\Domain\Identity\SessionLimiter;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function t7IdentityUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

function t7IdentityChild(User $parent): User
{
    return app(CreateChildAccount::class)->handle($parent, [
        'username' => 'kid-'.uniqid(),
        'password' => 'ChildPassword1!',
        'name_en' => 'Kid',
        'birth_year' => 2015,
        'birth_month' => 1,
    ]);
}

function t7Link(User $learner, User $parent): GuardianChildLink
{
    return app(RedeemLinkCode::class)->handle($parent, app(IssueLinkCode::class)->handle($learner));
}

it('normalises link codes before hashing and redeeming them', function (): void {
    expect(LinkCodeHasher::hash('  abcd2345 '))->toBe(LinkCodeHasher::hash('ABCD2345'))
        ->and(LinkCodeHasher::hash('ABCD2345'))->not->toBe(LinkCodeHasher::hash('ABCD2346'));

    $learner = t7IdentityUser('student');
    $parent = t7IdentityUser('parent');
    $code = app(IssueLinkCode::class)->handle($learner);

    app(RedeemLinkCode::class)->handle($parent, '  '.strtolower($code).' ');

    expect(GuardianChildLink::query()->sole()->child_id)->toBe($learner->id);
})->group('T-P1-06', 'A1', 'YG-GAP-FAM-002');

it('keeps redeemed link codes for audit while replacing only unused codes', function (): void {
    $learner = t7IdentityUser('student');
    $firstParent = t7IdentityUser('parent');
    $secondParent = t7IdentityUser('parent');

    $first = app(IssueLinkCode::class)->handle($learner);
    app(RedeemLinkCode::class)->handle($firstParent, $first);

    $second = app(IssueLinkCode::class)->handle($learner);
    $codes = DB::table('guardian_link_codes')->orderBy('id')->get();

    expect($codes)->toHaveCount(2)
        ->and($codes[0]->used_at)->not->toBeNull()
        ->and($codes[0]->redeemed_by)->toBe($firstParent->id)
        ->and($codes[1]->used_at)->toBeNull();

    expect(fn () => app(RedeemLinkCode::class)->handle($secondParent, $first))
        ->toThrow(ValidationException::class);
    app(RedeemLinkCode::class)->handle($secondParent, $second);

    expect(GuardianChildLink::query()->count())->toBe(2);
})->group('T-P1-06', 'A1', 'YG-GAP-FAM-002');

it('refuses link codes from suspended learners and codes issued before suspension', function (): void {
    $learner = t7IdentityUser('student');
    $parent = t7IdentityUser('parent');
    $code = app(IssueLinkCode::class)->handle($learner);

    $learner->forceFill(['status' => 'suspended'])->save();

    expect(fn () => app(RedeemLinkCode::class)->handle($parent, $code))
        ->toThrow(ValidationException::class)
        ->and(GuardianChildLink::query()->count())->toBe(0)
        ->and(fn () => app(IssueLinkCode::class)->handle($learner))
        ->toThrow(AuthorizationException::class);
})->group('T-P1-06', 'A3', 'I02');

it('does not let the same parent claim a learner twice', function (): void {
    $learner = t7IdentityUser('student');
    $parent = t7IdentityUser('parent');

    t7Link($learner, $parent);
    $second = app(IssueLinkCode::class)->handle($learner);

    try {
        app(RedeemLinkCode::class)->handle($parent, $second);
        $this->fail('A duplicate guardian link was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('code')
            ->and($exception->errors()['code'][0])->toBe(__('guardian.already_linked'));
    }

    expect(GuardianChildLink::query()->count())->toBe(1);
})->group('T-P1-06', 'A1');

it('requires a recent password confirmation before entering a child context', function (): void {
    config(['auth.password_timeout' => 300]);
    $parent = t7IdentityUser('parent');
    $child = t7IdentityChild($parent);
    $action = app(EnterChildContext::class);

    expect(fn () => $action->handle($parent, $child, null))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $action->handle($parent, $child, time() - 301))
        ->toThrow(AuthorizationException::class);
    expect(session('acting_child_id'))->toBeNull();

    $action->handle($parent, $child, time());

    expect(session('acting_child_id'))->toBe($child->id);
})->group('T-P1-06', 'A3', 'YG-GAP-FAM-004');

it('refuses to enter a child context through a pending or revoked link', function (): void {
    $parent = t7IdentityUser('parent');
    $child = t7IdentityChild($parent);
    $link = GuardianChildLink::query()->sole();
    $action = app(EnterChildContext::class);

    $link->forceFill(['status' => GuardianChildLink::STATUS_PENDING])->save();
    expect(fn () => $action->handle($parent, $child, time()))->toThrow(AuthorizationException::class);
    expect(session('acting_child_id'))->toBeNull();

    $link->forceFill(['status' => 'revoked'])->save();
    expect(fn () => $action->handle($parent, $child, time()))->toThrow(AuthorizationException::class);
    expect(session('acting_child_id'))->toBeNull();
})->group('T-P1-06', 'A3', 'I02');

it('rotates only an active session that belongs to the same user', function (): void {
    $user = t7IdentityUser('student');
    $other = t7IdentityUser('student');
    $limiter = app(SessionLimiter::class);
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36';

    expect($limiter->check($user, 'old', 'device-a', '203.0.113.5', $userAgent))->toBe(SessionLimiter::OK);
    $tracker = UserSession::query()->sole();

    expect($limiter->rotate($user, 'old', 'new'))->toBeTrue()
        ->and($limiter->rotate($user, 'old', 'replay'))->toBeFalse()
        ->and($limiter->rotate($other, 'new', 'stolen'))->toBeFalse();

    $tracker->refresh();
    expect($tracker->id)->toBe(UserSession::query()->sole()->id)
        ->and($tracker->session_hash)->toBe(hash('sha256', 'new'))
        ->and($tracker->user_id)->toBe($user->id);

    expect($limiter->rotate($user, 'new', 'third'))->toBeTrue();
    $limiter->end('third');
    expect($limiter->rotate($user, 'third', 'after-logout'))->toBeFalse()
        ->and(UserSession::query()->sole()->signed_out_reason)->toBe(UserSession::LOGOUT);
})->group('T-P4-07', 'A1', 'YG-GAP-SEC-001');

it('applies the device limit per learner instead of globally', function (): void {
    config(['security.sessions.enabled' => true, 'security.sessions.max_devices' => 1, 'security.sessions.max_concurrent' => 2]);
    $alice = t7IdentityUser('student');
    $bob = t7IdentityUser('student');
    $limiter = app(SessionLimiter::class);
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36';

    expect($limiter->check($alice, 'alice-1', 'shared-token', '203.0.113.5', $userAgent))->toBe(SessionLimiter::OK)
        ->and($limiter->check($bob, 'bob-1', 'shared-token', '203.0.113.6', $userAgent))->toBe(SessionLimiter::OK)
        ->and($limiter->check($alice, 'alice-2', 'alice-second', '203.0.113.5', $userAgent))->toBe(SessionLimiter::DEVICE_LIMIT);

    expect(UserDevice::query()->where('user_id', $alice->id)->count())->toBe(1)
        ->and(UserDevice::query()->where('user_id', $bob->id)->count())->toBe(1);
})->group('T-P4-07', 'A1');

it('refreshes the stored IP only after the touch interval', function (): void {
    config(['security.sessions.enabled' => true, 'security.sessions.max_devices' => 2, 'security.sessions.max_concurrent' => 2, 'security.sessions.touch_seconds' => 60, 'security.sessions.idle_minutes' => 120]);
    $this->travelTo(now()->startOfSecond());
    $user = t7IdentityUser('student');
    $limiter = app(SessionLimiter::class);
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36';

    expect($limiter->check($user, 'session-a', 'device-a', '198.51.100.1', $userAgent))->toBe(SessionLimiter::OK);
    $tracker = UserSession::query()->sole();
    $initial = $tracker->last_active_at;

    $this->travel(30)->seconds();
    expect($limiter->check($user, 'session-a', 'device-a', '198.51.100.2', $userAgent))->toBe(SessionLimiter::OK);
    expect($tracker->refresh()->ip)->toBe('198.51.100.1')
        ->and($tracker->last_active_at->equalTo($initial))->toBeTrue();

    $this->travel(31)->seconds();
    expect($limiter->check($user, 'session-a', 'device-a', '198.51.100.3', $userAgent))->toBe(SessionLimiter::OK);
    expect($tracker->refresh()->ip)->toBe('198.51.100.3')
        ->and($tracker->last_active_at->greaterThan($initial))->toBeTrue();
})->group('T-P4-07', 'A1');

it('labels common mobile devices and browsers', function (): void {
    config(['security.sessions.enabled' => true, 'security.sessions.max_devices' => 3, 'security.sessions.max_concurrent' => 3]);
    $user = t7IdentityUser('student');
    $limiter = app(SessionLimiter::class);

    $limiter->check($user, 'ios', 'device-ios', '203.0.113.5', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Version/17.0 Mobile/15E148 Safari/604.1');
    $limiter->check($user, 'android', 'device-android', '203.0.113.6', 'Mozilla/5.0 (Android 14; Mobile; rv:120.0) Gecko/120.0 Firefox/120.0');

    $labels = UserDevice::query()->orderBy('id')->pluck('label');
    expect($labels[0])->toContain('iOS')->toContain('Safari')
        ->and($labels[1])->toContain('Android')->toContain('Firefox');
})->group('T-P4-07', 'A1');

it('replaces both previous avatar renditions when a new avatar is uploaded', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $user = t7IdentityUser('student');

    $this->actingAs($user)->postJson('/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('first.jpg', 640, 480),
    ])->assertNoContent();
    $first = $user->refresh()->getAttribute('avatar_path');

    $this->postJson('/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('second.jpg', 320, 240),
    ])->assertNoContent();
    $second = $user->refresh()->getAttribute('avatar_path');

    expect($second)->not->toBe($first);
    foreach (['local', 'public'] as $disk) {
        Storage::disk($disk)->assertMissing($first);
        Storage::disk($disk)->assertExists($second);
    }
})->group('T-P1-02', 'A5', 'YG-GAP-SEC-004');

it('rejects avatars with excessive dimensions or a non-image payload', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $user = t7IdentityUser('student');

    $this->actingAs($user)->postJson('/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('wide.jpg', 2049, 10),
    ])->assertUnprocessable()->assertJsonValidationErrors('avatar');

    $this->postJson('/profile/avatar', [
        'avatar' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
    ])->assertUnprocessable()->assertJsonValidationErrors('avatar');

    expect($user->refresh()->getAttribute('avatar_path'))->toBeNull();
})->group('T-P1-02', 'A5', 'YG-GAP-SEC-004');

it('rejects a protocol-relative locale return target', function (): void {
    $this->postJson('/locale', ['locale' => 'en', 'return_to' => '//evil.example'])
        ->assertUnprocessable()->assertJsonValidationErrors('return_to');
    $this->postJson('/locale', ['locale' => 'en', 'return_to' => '/\\evil.example'])
        ->assertUnprocessable()->assertJsonValidationErrors('return_to');

    $this->post('/locale', ['locale' => 'en', 'return_to' => '/en/courses'])
        ->assertRedirect('/en/courses');
})->group('T-P1-01', 'A2', 'D-011');
