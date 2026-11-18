<?php

declare(strict_types=1);

namespace App\Domain\Guardians\Queries;

use App\Domain\Guardians\Models\GuardianVerification;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;

/**
 * O-015: "verified guardian" = an active parent account with a verified email AND a confirmed 6-digit code. This is the single
 * question the minors' gate and the legal review ask. A child counts as guardian-verified when at least one verified-link guardian is.
 */
final class GuardianVerified
{
    public static function forGuardian(User $guardian): bool
    {
        return $guardian->getAttribute('status') === 'active'
            && $guardian->hasVerifiedEmail()
            && GuardianVerification::query()->where('user_id', $guardian->getKey())->whereNotNull('verified_at')->exists();
    }

    public static function forChild(User $child): bool
    {
        $guardians = User::query()->whereIn('id', GuardianChildLink::query()
            ->where('child_id', $child->getKey())->where('status', GuardianChildLink::STATUS_VERIFIED)->select('guardian_id'))->get();

        return $guardians->contains(static fn (User $guardian): bool => self::forGuardian($guardian));
    }

    /** @return array{verified: int, pending: int} guardians with at least one linked child, split by verification, for the legal review */
    public static function counts(): array
    {
        $guardians = User::query()->whereIn('id', GuardianChildLink::query()->where('status', GuardianChildLink::STATUS_VERIFIED)->select('guardian_id'))->get();
        $verified = $guardians->filter(static fn (User $guardian): bool => self::forGuardian($guardian))->count();

        return ['verified' => $verified, 'pending' => $guardians->count() - $verified];
    }
}
