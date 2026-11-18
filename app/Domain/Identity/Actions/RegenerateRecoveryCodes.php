<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\TwoFactor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RegenerateRecoveryCodes
{
    public function __construct(private readonly TwoFactor $twoFactor, private readonly AuditLogger $audit) {}

    /** @return list<string> */
    public function handle(User $user): array
    {
        if ($user->two_factor_confirmed_at === null) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user): array {
            $codes = $this->twoFactor->newRecoveryCodes();
            $user->forceFill(['two_factor_recovery_codes' => array_map(TwoFactor::hashRecoveryCode(...), $codes)])->save();
            if ($this->audit->record('auth.two_factor_recovery_regenerated', $user, (int) $user->getKey()) === null) {
                throw new RuntimeException('Unable to record recovery code audit.');
            }

            return $codes;
        });
    }
}
