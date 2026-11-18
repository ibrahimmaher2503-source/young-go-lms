<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Instructors\Models\PayoutProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

/**
 * Finance reads the full payout details (needs `payout_profile.reveal`). Every reveal is audited with who and which profile
 * (never the values) BEFORE the details are returned, so a failed audit reveals nothing.
 */
final class RevealPayoutProfile
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, string> */
    public function __invoke(User $actor, PayoutProfile $profile): array
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payout_profile.reveal')) {
            throw new AuthorizationException;
        }
        if ($this->audit->record('payout_profile.revealed', $profile, (int) $actor->getKey(), null, null, ['method' => $profile->method]) === null) {
            throw new RuntimeException('Unable to record payout profile audit.');
        }

        return $profile->details;
    }
}
