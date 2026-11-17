<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Session;

/** Per-session proof that the signed-in user passed the second factor. Bound to the user id. */
final class TwoFactorSession
{
    public const string KEY = 'two_factor_verified_user_id';

    public static function markVerified(User $user): void
    {
        Session::put(self::KEY, (int) $user->getKey());
    }

    public static function isVerified(User $user): bool
    {
        return Session::get(self::KEY) === (int) $user->getKey();
    }
}
