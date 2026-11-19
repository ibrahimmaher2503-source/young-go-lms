<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\TwoFactor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ConfirmTwoFactorEnrollment
{
    public function __construct(private readonly TwoFactor $twoFactor, private readonly AuditLogger $audit) {}

    /** @return array{user: User, recoveryCodes: list<string>} recovery codes are shown exactly once */
    public function handle(User $user, string $code): array
    {
        return DB::transaction(function () use ($user, $code): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $secret = $locked->two_factor_secret;
            if ($locked->two_factor_confirmed_at !== null || ! is_string($secret) || $secret === '') {
                throw ValidationException::withMessages(['code' => __('auth.two_factor_invalid')]);
            }

            $step = $this->twoFactor->verify($secret, $code, null);
            if ($step === null) {
                throw ValidationException::withMessages(['code' => __('auth.two_factor_invalid')]);
            }

            $codes = $this->twoFactor->newRecoveryCodes();
            $locked->forceFill([
                'two_factor_confirmed_at' => now(),
                'two_factor_last_step' => $step,
                'two_factor_recovery_codes' => array_map(TwoFactor::hashRecoveryCode(...), $codes),
            ])->save();

            if ($this->audit->record('auth.two_factor_enabled', $locked, (int) $locked->getKey()) === null) {
                throw new RuntimeException('Unable to record two-factor audit.');
            }

            return ['user' => $locked, 'recoveryCodes' => $codes];
        });
    }
}
