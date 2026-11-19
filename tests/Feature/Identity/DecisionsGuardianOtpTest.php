<?php

declare(strict_types=1);

// T-DECISIONS-2 | O-015 | A1 A2

use App\Domain\Guardians\Actions\ConfirmGuardianOtp;
use App\Domain\Guardians\Actions\SendGuardianOtp;
use App\Domain\Guardians\Models\GuardianVerification;
use App\Domain\Guardians\Notifications\GuardianOtpNotice;
use App\Domain\Guardians\Queries\GuardianVerified;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** An active parent with a verified email (the O-015 precondition). */
function dgoParent(bool $verifiedEmail = true): User
{
    return User::factory()->create([
        'status' => 'active', 'email_verified_at' => $verifiedEmail ? now() : null,
    ])->assignRole('parent');
}

/** The 6-digit code the notice carries; read from the notification, never from the database (only a hash is stored). */
function dgoSentCode(User $parent): string
{
    $code = null;
    Notification::assertSentTo($parent, GuardianOtpNotice::class, function (GuardianOtpNotice $notice) use (&$code): bool {
        $code = (new ReflectionProperty(GuardianOtpNotice::class, 'code'))->getValue($notice);

        return true;
    });

    return (string) $code;
}

function dgoValidationKey(callable $run): ?string
{
    try {
        $run();
    } catch (ValidationException $exception) {
        return (string) ($exception->errors()['code'][0] ?? '');
    }

    return null;
}

// ---------------------------------------------------------------- A1: sending the code

it('refuses to send a code to a user who is not an active parent', function (): void {
    $student = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('student');

    expect(fn () => app(SendGuardianOtp::class)->handle($student))->toThrow(AuthorizationException::class);
})->group('T-DECISIONS-2', 'O-015', 'A1');

it('refuses to send a code while the parent email is not verified', function (): void {
    $parent = dgoParent(false);

    $message = dgoValidationKey(fn () => app(SendGuardianOtp::class)->handle($parent));

    expect($message)->toBe(__('guardian.otp.verify_email_first'))
        ->and(GuardianVerification::query()->count())->toBe(0);
    Notification::fake();
})->group('T-DECISIONS-2', 'O-015', 'A1');

it('mails a six digit code by email only, stores only its hash and expires it in 10 minutes', function (): void {
    Notification::fake();
    $parent = dgoParent();

    app(SendGuardianOtp::class)->handle($parent);

    $code = dgoSentCode($parent);
    Notification::assertSentTo($parent, GuardianOtpNotice::class, function (GuardianOtpNotice $notice, array $channels): bool {
        return $channels === ['mail'];
    });
    $row = GuardianVerification::query()->where('user_id', $parent->id)->sole();

    expect($code)->toMatch('/^\d{6}$/')
        ->and($row->code_hash)->not->toBe($code)
        ->and($row->code_hash)->toBe(GuardianVerification::hashCode($parent->id, $code))
        ->and($row->attempts)->toBe(0)
        ->and($row->code_expires_at->equalTo(now()->addMinutes(10)))->toBeTrue();
})->group('T-DECISIONS-2', 'O-015', 'A1');

it('refuses a second code within 60 seconds and accepts one after the gap', function (): void {
    Notification::fake();
    $parent = dgoParent();
    $sender = app(SendGuardianOtp::class);
    $sender->handle($parent);

    $wait = dgoValidationKey(fn () => $sender->handle($parent));
    expect($wait)->toBe(__('guardian.otp.wait', ['seconds' => 60]));

    Carbon::setTestNow(now()->addSeconds(61));
    $sender->handle($parent);
    Notification::assertSentToTimes($parent, GuardianOtpNotice::class, 2);
})->group('T-DECISIONS-2', 'O-015', 'A1');

it('refuses a sixth code within one hour and writes the audit without the code', function (): void {
    Notification::fake();
    $parent = dgoParent();
    $sender = app(SendGuardianOtp::class);
    for ($i = 0; $i < 5; $i++) {
        $sender->handle($parent);
        Carbon::setTestNow(now()->addSeconds(61));
    }

    $message = dgoValidationKey(fn () => $sender->handle($parent));

    expect($message)->toBe(__('guardian.otp.too_many_sends'))
        ->and(Activity::query()->where('event', 'guardian.otp_sent')->count())->toBe(5);

    $payload = json_encode(Activity::query()->where('event', 'guardian.otp_sent')->get()->pluck('properties')->all());
    Notification::assertSentToTimes($parent, GuardianOtpNotice::class, 5);
    expect(RateLimiter::attempts('guardian-otp-send:'.$parent->id))->toBe(5)
        ->and($payload)->not->toMatch('/"\d{6}"/');
})->group('T-DECISIONS-2', 'O-015', 'A1');

// ---------------------------------------------------------------- A1: confirming the code

it('verifies the guardian on the right code, clears the hash and audits guardian.verified', function (): void {
    Notification::fake();
    $parent = dgoParent();
    app(SendGuardianOtp::class)->handle($parent);

    app(ConfirmGuardianOtp::class)->handle($parent, dgoSentCode($parent));

    $row = GuardianVerification::query()->where('user_id', $parent->id)->sole();
    expect($row->verified_at)->not->toBeNull()
        ->and($row->code_hash)->toBeNull()
        ->and($row->code_expires_at)->toBeNull()
        ->and(Activity::query()->where('event', 'guardian.verified')->count())->toBe(1);
})->group('T-DECISIONS-2', 'O-015', 'A1');

it('counts a wrong code as an attempt, audits guardian.otp_failed and keeps the counter after the refusal', function (): void {
    Notification::fake();
    $parent = dgoParent();
    app(SendGuardianOtp::class)->handle($parent);
    $right = dgoSentCode($parent);
    $wrong = $right === '000000' ? '111111' : '000000';

    $message = dgoValidationKey(fn () => app(ConfirmGuardianOtp::class)->handle($parent, $wrong));

    expect($message)->toBe(__('guardian.otp.wrong'))
        ->and(GuardianVerification::query()->where('user_id', $parent->id)->value('attempts'))->toBe(1)
        ->and(Activity::query()->where('event', 'guardian.otp_failed')->count())->toBe(1)
        ->and(GuardianVerification::query()->where('user_id', $parent->id)->value('verified_at'))->toBeNull();
})->group('T-DECISIONS-2', 'O-015', 'A1');

it('locks the code after five wrong tries, so even the right code is refused until a new code is sent', function (): void {
    Notification::fake();
    $parent = dgoParent();
    app(SendGuardianOtp::class)->handle($parent);
    $right = dgoSentCode($parent);
    $wrong = $right === '000000' ? '111111' : '000000';
    $confirm = app(ConfirmGuardianOtp::class);

    for ($i = 0; $i < 5; $i++) {
        dgoValidationKey(fn () => $confirm->handle($parent, $wrong));
    }
    $locked = dgoValidationKey(fn () => $confirm->handle($parent, $right));
    expect($locked)->toBe(__('guardian.otp.locked'))
        ->and(GuardianVerification::query()->where('user_id', $parent->id)->value('verified_at'))->toBeNull();

    Carbon::setTestNow(now()->addSeconds(61));
    app(SendGuardianOtp::class)->handle($parent);
    $fresh = dgoSentCode($parent);
    $confirm->handle($parent, $fresh);
    expect(GuardianVerification::query()->where('user_id', $parent->id)->value('verified_at'))->not->toBeNull();
})->group('T-DECISIONS-2', 'O-015', 'A1');

it('refuses an expired code and a parent who never requested one', function (): void {
    Notification::fake();
    $never = dgoParent();
    expect(dgoValidationKey(fn () => app(ConfirmGuardianOtp::class)->handle($never, '123456')))->toBe(__('guardian.otp.expired'));

    $parent = dgoParent();
    app(SendGuardianOtp::class)->handle($parent);
    $code = dgoSentCode($parent);
    Carbon::setTestNow(now()->addMinutes(11));

    expect(dgoValidationKey(fn () => app(ConfirmGuardianOtp::class)->handle($parent, $code)))->toBe(__('guardian.otp.expired'))
        ->and(GuardianVerification::query()->where('user_id', $parent->id)->value('verified_at'))->toBeNull();
})->group('T-DECISIONS-2', 'O-015', 'A1');

it('refuses input that is not exactly six digits', function (): void {
    Notification::fake();
    $parent = dgoParent();
    app(SendGuardianOtp::class)->handle($parent);
    $code = dgoSentCode($parent);

    expect(dgoValidationKey(fn () => app(ConfirmGuardianOtp::class)->handle($parent, 'abcdef')))->toBe(__('guardian.otp.wrong'))
        ->and(dgoValidationKey(fn () => app(ConfirmGuardianOtp::class)->handle($parent, substr($code, 0, 5))))->toBe(__('guardian.otp.wrong'))
        ->and(GuardianVerification::query()->where('user_id', $parent->id)->value('verified_at'))->toBeNull();
})->group('T-DECISIONS-2', 'O-015', 'A1');

// ---------------------------------------------------------------- A2: verified queries

it('counts a guardian as verified only with an active account, a verified email and a confirmed code', function (): void {
    Notification::fake();
    $parent = dgoParent();
    expect(GuardianVerified::forGuardian($parent))->toBeFalse();

    app(SendGuardianOtp::class)->handle($parent);
    expect(GuardianVerified::forGuardian($parent))->toBeFalse();

    app(ConfirmGuardianOtp::class)->handle($parent, dgoSentCode($parent));
    expect(GuardianVerified::forGuardian($parent))->toBeTrue();

    $parent->forceFill(['email_verified_at' => null])->save();
    expect(GuardianVerified::forGuardian($parent->fresh()))->toBeFalse();
})->group('T-DECISIONS-2', 'O-015', 'A2');

it('treats a child as guardian-verified only through a verified link to a verified guardian', function (): void {
    Notification::fake();
    $child = User::factory()->create(['status' => 'active'])->assignRole('student');
    $parent = dgoParent();
    $link = GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_PENDING, 'method' => 'link_code',
    ]);

    expect(GuardianVerified::forChild($child))->toBeFalse();

    app(SendGuardianOtp::class)->handle($parent);
    app(ConfirmGuardianOtp::class)->handle($parent, dgoSentCode($parent));
    expect(GuardianVerified::forChild($child))->toBeFalse();

    $link->forceFill(['status' => GuardianChildLink::STATUS_VERIFIED])->save();
    expect(GuardianVerified::forChild($child))->toBeTrue();
})->group('T-DECISIONS-2', 'O-015', 'A2');

it('reports verified and pending guardians in counts()', function (): void {
    Notification::fake();
    $child = User::factory()->create(['status' => 'active'])->assignRole('student');
    $verifiedParent = dgoParent();
    $pendingParent = dgoParent();
    GuardianChildLink::query()->create(['guardian_id' => $verifiedParent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED, 'method' => 'link_code']);
    GuardianChildLink::query()->create(['guardian_id' => $pendingParent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED, 'method' => 'link_code']);
    app(SendGuardianOtp::class)->handle($verifiedParent);
    app(ConfirmGuardianOtp::class)->handle($verifiedParent, dgoSentCode($verifiedParent));

    expect(GuardianVerified::counts())->toBe(['verified' => 1, 'pending' => 1]);
})->group('T-DECISIONS-2', 'O-015', 'A2');
