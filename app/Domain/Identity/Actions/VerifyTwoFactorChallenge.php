<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\TwoFactor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class VerifyTwoFactorChallenge
{
    public function __construct(private readonly TwoFactor $twoFactor, private readonly AuditLogger $audit) {}

    /** Accepts a current TOTP code (each time step once) or an unused recovery code. */
    public function handle(User $user, string $code): void
    {
        $passed = DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $secret = $locked->two_factor_secret;
            if ($locked->two_factor_confirmed_at === null || ! is_string($secret) || $secret === '') {
                return false;
            }

            $step = $this->twoFactor->verify($secret, $code, $locked->two_factor_last_step);
            if ($step !== null) {
                $locked->forceFill(['two_factor_last_step' => $step])->save();

                return true;
            }

            $hash = TwoFactor::hashRecoveryCode($code);
            $stored = is_array($locked->two_factor_recovery_codes) ? $locked->two_factor_recovery_codes : [];
            $index = array_search($hash, $stored, true);
            if ($index === false) {
                return false;
            }

            unset($stored[$index]);
            $locked->forceFill(['two_factor_recovery_codes' => array_values($stored)])->save();
            if ($this->audit->record('auth.two_factor_recovery_used', $locked, (int) $locked->getKey(), null, ['remaining' => count($stored)]) === null) {
                throw new RuntimeException('Unable to record recovery code audit.');
            }

            return true;
        });

        if (! $passed) {
            throw ValidationException::withMessages(['code' => __('auth.two_factor_invalid')]);
        }
    }
}
