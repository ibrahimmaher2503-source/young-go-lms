<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\TwoFactor;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class BeginTwoFactorEnrollment
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    /** Idempotent: keeps an unconfirmed secret so the QR code stays stable while the user scans it. */
    public function handle(User $user): string
    {
        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages(['code' => __('auth.two_factor_already_enabled')]);
        }

        if (! is_string($user->two_factor_secret) || $user->two_factor_secret === '') {
            $user->forceFill(['two_factor_secret' => $this->twoFactor->newSecret(), 'two_factor_last_step' => null])->save();
        }

        return (string) $user->two_factor_secret;
    }
}
