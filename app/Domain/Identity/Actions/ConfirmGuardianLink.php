<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ConfirmGuardianLink
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $learner, int $linkId): GuardianChildLink
    {
        return DB::transaction(function () use ($learner, $linkId): GuardianChildLink {
            // Not found for anyone but the linked learner, and only while pending.
            $link = GuardianChildLink::query()
                ->whereKey($linkId)
                ->where('child_id', $learner->getKey())
                ->where('status', GuardianChildLink::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if ($link === null || $learner->getAttribute('status') !== 'active') {
                throw (new ModelNotFoundException)->setModel(GuardianChildLink::class, [$linkId]);
            }

            $link->forceFill(['status' => GuardianChildLink::STATUS_VERIFIED, 'verified_at' => now()])->save();

            if ($this->audit->record('guardian.link_confirmed', $learner, (int) $learner->getKey(), ['status' => 'pending'], ['status' => 'verified'], ['link_id' => $link->getKey()]) === null) {
                throw new RuntimeException('Unable to record link confirmation audit.');
            }

            return $link;
        });
    }
}
