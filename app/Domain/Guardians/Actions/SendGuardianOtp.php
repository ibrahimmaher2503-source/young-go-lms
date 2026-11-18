<?php

declare(strict_types=1);

namespace App\Domain\Guardians\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Guardians\Models\GuardianVerification;
use App\Domain\Guardians\Notifications\GuardianOtpNotice;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * O-015: sends a fresh 6-digit code to the parent's verified email. The code is hashed at rest and expires; a new code resets the attempts.
 * Throttled per guardian: a minimum gap between codes and a cap per hour (on top of the route throttle).
 */
final class SendGuardianOtp
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $guardian): GuardianVerification
    {
        if ($guardian->getAttribute('status') !== 'active' || ! $guardian->hasRole('parent')) {
            throw new AuthorizationException;
        }
        if (! $guardian->hasVerifiedEmail() || ! filled($guardian->getAttribute('email'))) {
            throw ValidationException::withMessages(['code' => __('guardian.otp.verify_email_first')]);
        }

        $minutes = self::setting('guardians.otp.expires_minutes', 10);
        $gap = self::setting('guardians.otp.resend_seconds', 60);
        $perHour = self::setting('guardians.otp.max_sends_per_hour', 5);
        $limiterKey = 'guardian-otp-send:'.$guardian->getKey();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verification = DB::transaction(function () use ($guardian, $code, $minutes, $gap, $perHour, $limiterKey): GuardianVerification {
            DB::table('guardian_verifications')->insertOrIgnore(['user_id' => $guardian->getKey(), 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $row = GuardianVerification::query()->where('user_id', $guardian->getKey())->lockForUpdate()->firstOrFail();

            if ($row->code_sent_at !== null && $row->code_sent_at->diffInSeconds(now(), true) < $gap) {
                throw ValidationException::withMessages(['code' => __('guardian.otp.wait', ['seconds' => $gap])]);
            }
            if (RateLimiter::tooManyAttempts($limiterKey, $perHour)) {
                throw ValidationException::withMessages(['code' => __('guardian.otp.too_many_sends')]);
            }
            RateLimiter::hit($limiterKey, 3600);

            $row->forceFill([
                'code_hash' => GuardianVerification::hashCode((int) $guardian->getKey(), $code), 'code_expires_at' => now()->addMinutes($minutes),
                'attempts' => 0, 'code_sent_at' => now(),
            ])->save();

            // The code itself is never audited.
            if ($this->audit->record('guardian.otp_sent', $guardian, (int) $guardian->getKey(), null, null, ['expires_minutes' => $minutes]) === null) {
                throw new RuntimeException('Unable to record guardian code audit.');
            }

            return $row;
        });

        $guardian->notify(new GuardianOtpNotice($code, $minutes));

        return $verification;
    }

    private static function setting(string $key, int $default): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
