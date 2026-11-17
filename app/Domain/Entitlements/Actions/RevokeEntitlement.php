<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Takes access away immediately and keeps the row for history. Needs the grant permission and a reason. */
final class RevokeEntitlement
{
    public function __construct(private readonly AuditLogger $audit, private readonly EntitlementService $service) {}

    public function __invoke(User $actor, Entitlement $entitlement, string $reason): Entitlement
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('manual_entitlement.grant')) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('access.reason_invalid')]);
        }

        $result = DB::transaction(function () use ($actor, $entitlement, $reason): Entitlement {
            $current = Entitlement::query()->lockForUpdate()->findOrFail($entitlement->getKey());
            if ($current->status === Entitlement::REVOKED) {
                return $current; // already revoked: nothing to change
            }

            $current->forceFill(['status' => Entitlement::REVOKED, 'revoked_by' => $actor->getKey(), 'revoked_at' => now(), 'reason' => $reason])->save();
            if ($this->audit->record('entitlement.revoked', $current->course, (int) $actor->getKey(), null, ['learner_id' => $current->learner_id, 'source' => $current->source_type->value]) === null) {
                throw new RuntimeException('Unable to record entitlement audit.');
            }

            return $current;
        });

        $this->service->flush();

        return $result;
    }
}
