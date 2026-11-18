<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class GuardianAccess
{
    public static function parentCanAccessChild(User $parent, User $child): Response
    {
        $linked = $parent->getAttribute('status') === 'active'
            && $parent->hasRole('parent')
            && GuardianChildLink::query()
                ->where('guardian_id', $parent->getKey())
                ->where('child_id', $child->getKey())
                ->where('status', GuardianChildLink::STATUS_VERIFIED)
                ->exists();

        return $linked ? Response::allow() : Response::denyAsNotFound();
    }

    /** Fails closed when the user is guardian-linked, has no email (parent-created), or is under the adult threshold. */
    public static function isMinor(User $user): bool
    {
        if (GuardianChildLink::query()->where('child_id', $user->getKey())->exists()) {
            return true;
        }
        if ($user->getAttribute('email') === null) {
            return true;
        }

        $year = $user->getAttribute('birth_year');
        if ($year === null) {
            return false;
        }

        $adultAge = config('ai.adult_age');
        $threshold = is_numeric($adultAge) ? (int) $adultAge : 18;
        $month = (int) ($user->getAttribute('birth_month') ?? 12);
        $now = now();

        return $now->year - (int) $year - ($now->month < $month ? 1 : 0) < $threshold;
    }
}
