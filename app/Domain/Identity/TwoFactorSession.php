<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Session;

/** Per-session proof bound to the user and the specific confirmed enrollment. */
final class TwoFactorSession
{
    public const string KEY = 'two_factor_verified_user_id';

    public static function markVerified(User $user): void
    {
        Session::put(self::KEY, self::enrollmentFingerprint($user));
    }

    public static function isVerified(User $user): bool
    {
        $fingerprint = self::enrollmentFingerprint($user);
        $verified = Session::get(self::KEY);

        return $fingerprint !== null && is_string($verified) && hash_equals($fingerprint, $verified);
    }

    private static function enrollmentFingerprint(User $user): ?string
    {
        $encryptedSecret = $user->getRawOriginal('two_factor_secret');
        if (! $user->hasTwoFactorEnabled() || ! is_string($encryptedSecret) || $encryptedSecret === '') {
            return null;
        }

        return hash('sha256', $user->getKey().':'.$encryptedSecret);
    }
}
