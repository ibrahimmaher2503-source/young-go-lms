<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

final class DisableTwoFactor
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Roles that must use 2FA cannot turn it off themselves; $actorId null means an operator CLI reset. */
    public function handle(User $user, ?int $actorId): void
    {
        if ($actorId !== null && $user->requiresTwoFactor()) {
            throw new AuthorizationException;
        }

        if ($user->getAttribute('two_factor_secret') === null && ! $user->hasTwoFactorEnabled()) {
            return;
        }

        $user->forceFill([
            'two_factor_secret' => null, 'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null, 'two_factor_last_step' => null,
        ])->save();

        if ($this->audit->record('auth.two_factor_disabled', $user, $actorId, null, null, ['via' => $actorId === null ? 'cli' : 'self']) === null) {
            throw new RuntimeException('Unable to record two-factor audit.');
        }
    }
}
