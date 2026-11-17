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
}
