<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\LinkCodeHasher;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class RedeemLinkCode
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $parent, string $code): GuardianChildLink
    {
        if ($parent->getAttribute('status') !== 'active' || ! $parent->hasRole('parent') || ! $parent->can('guardian.child.manage')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($parent, $code): GuardianChildLink {
            $row = DB::table('guardian_link_codes')
                ->where('code_hash', LinkCodeHasher::hash($code))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            $learner = $row === null ? null : User::query()->find($row->learner_id);
            if ($row === null || $learner === null || $learner->getAttribute('status') !== 'active' || $learner->is($parent)) {
                throw ValidationException::withMessages(['code' => __('guardian.code_invalid')]);
            }

            if (GuardianChildLink::query()->where('guardian_id', $parent->getKey())->where('child_id', $learner->getKey())->exists()) {
                throw ValidationException::withMessages(['code' => __('guardian.already_linked')]);
            }

            DB::table('guardian_link_codes')->where('id', $row->id)->update([
                'used_at' => now(), 'redeemed_by' => $parent->getKey(), 'updated_at' => now(),
            ]);

            $link = GuardianChildLink::query()->create([
                'guardian_id' => $parent->getKey(),
                'child_id' => $learner->getKey(),
                'status' => GuardianChildLink::STATUS_PENDING,
                'method' => GuardianChildLink::METHOD_LINK_CODE,
            ]);

            if ($this->audit->record('guardian.link_requested', $learner, (int) $parent->getKey(), null, ['link_id' => $link->getKey()]) === null) {
                throw new RuntimeException('Unable to record link request audit.');
            }

            return $link;
        });
    }
}
