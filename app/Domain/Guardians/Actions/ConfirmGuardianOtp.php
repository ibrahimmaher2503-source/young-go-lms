<?php

declare(strict_types=1);

namespace App\Domain\Guardians\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Guardians\Models\GuardianVerification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * O-015: confirms the 6-digit code. At most `guardians.otp.max_attempts` wrong tries per code; after that (or after expiry) a new code is
 * needed. A wrong try is counted and audited before the refusal is thrown, so the counter survives the error.
 */
final class ConfirmGuardianOtp
{
    private const string OK = 'ok';

    private const string WRONG = 'wrong';

    private const string LOCKED = 'locked';

    private const string EXPIRED = 'expired';

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $guardian, string $code): GuardianVerification
    {
        if ($guardian->getAttribute('status') !== 'active' || ! $guardian->hasRole('parent')) {
            throw new AuthorizationException;
        }
        if (! $guardian->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['code' => __('guardian.otp.verify_email_first')]);
        }

        $code = trim($code);
        $configured = config('guardians.otp.max_attempts');
        $max = is_numeric($configured) ? (int) $configured : 5;

        [$outcome, $row] = DB::transaction(function () use ($guardian, $code, $max): array {
            $row = GuardianVerification::query()->where('user_id', $guardian->getKey())->lockForUpdate()->first();
            if ($row === null || ! $row->hasPendingCode()) {
                return [self::EXPIRED, $row];
            }
            if ($row->attempts >= $max) {
                return [self::LOCKED, $row];
            }

            $matches = preg_match('/^\d{6}$/', $code) === 1 && hash_equals((string) $row->code_hash, GuardianVerification::hashCode((int) $guardian->getKey(), $code));
            if (! $matches) {
                $row->forceFill(['attempts' => $row->attempts + 1])->save();
                if ($this->audit->record('guardian.otp_failed', $guardian, (int) $guardian->getKey(), null, null, ['attempts' => $row->attempts]) === null) {
                    throw new RuntimeException('Unable to record guardian code audit.');
                }

                return [self::WRONG, $row];
            }

            $row->forceFill(['verified_at' => now(), 'code_hash' => null, 'code_expires_at' => null, 'attempts' => 0])->save();
            if ($this->audit->record('guardian.verified', $guardian, (int) $guardian->getKey()) === null) {
                throw new RuntimeException('Unable to record guardian verification audit.');
            }

            return [self::OK, $row];
        });

        return match ($outcome) {
            self::OK => $row ?? throw new RuntimeException('Guardian verification row missing.'),
            self::LOCKED => throw ValidationException::withMessages(['code' => __('guardian.otp.locked')]),
            self::WRONG => throw ValidationException::withMessages(['code' => __('guardian.otp.wrong')]),
            default => throw ValidationException::withMessages(['code' => __('guardian.otp.expired')]),
        };
    }
}
