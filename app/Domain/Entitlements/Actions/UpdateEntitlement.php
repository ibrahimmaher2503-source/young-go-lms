<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Updates the access window (`starts_at`, `ends_at`/lifetime) and reason of a non-revoked course entitlement.
 * Lives in the Entitlements module so only this module mutates the entitlements table (I01).
 */
final class UpdateEntitlement
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EntitlementService $service,
        private readonly SendAccessNotice $notice,
    ) {}

    public function __invoke(
        User $actor,
        Entitlement $entitlement,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        string $reason,
    ): Entitlement {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('manual_entitlement.grant')) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('access.reason_invalid')]);
        }

        if ($endsAt !== null && $endsAt->lte($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => __('access.ends_before_start')]);
        }

        $result = DB::transaction(function () use ($actor, $entitlement, $startsAt, $endsAt, $reason): Entitlement {
            $current = Entitlement::query()->lockForUpdate()->findOrFail($entitlement->getKey());
            if ($current->status === Entitlement::REVOKED) {
                throw ValidationException::withMessages(['status' => __('entitlements.cannot_edit_revoked')]);
            }

            $hadAccess = $this->service->canAccessCourse($current->learner, $current->course)->allowed;
            $before = [
                'status' => $current->status,
                'starts_at' => $current->starts_at->toIso8601String(),
                'ends_at' => $current->ends_at?->toIso8601String(),
            ];

            $newStatus = ($endsAt !== null && $endsAt->lte(now())) ? Entitlement::EXPIRED : Entitlement::ACTIVE;

            $current->forceFill([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $newStatus,
                'reason' => $reason,
            ])->save();

            $after = [
                'learner_id' => $current->learner_id,
                'source' => $current->source_type->value,
                'status' => $current->status,
                'starts_at' => $current->starts_at->toIso8601String(),
                'ends_at' => $current->ends_at?->toIso8601String(),
            ];

            if ($this->audit->record('entitlement.updated', $current->course, (int) $actor->getKey(), $before, $after) === null) {
                throw new RuntimeException('Unable to record entitlement update audit.');
            }

            $this->service->flush();
            if (! $hadAccess && $current->isCurrent() && $this->service->canAccessCourse($current->learner, $current->course)->allowed) {
                $this->notice->afterCommit($current, granted: true);
            }

            return $current;
        });

        $this->service->flush();

        return $result;
    }
}
