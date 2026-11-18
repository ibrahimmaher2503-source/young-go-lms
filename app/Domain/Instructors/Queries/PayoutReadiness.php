<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Queries;

use App\Domain\Instructors\Models\PayoutProfile;
use App\Models\User;

/** YG-GAP-INS-003: an instructor may be settled only once a payout profile exists. Settlement code must call this before paying out. */
final class PayoutReadiness
{
    public static function isReady(User $instructor): bool
    {
        return PayoutProfile::query()->where('user_id', $instructor->getKey())->exists();
    }
}
