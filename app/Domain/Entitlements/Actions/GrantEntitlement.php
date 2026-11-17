<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Gives a learner access to a course. Idempotent: the same source, reference, learner and course is one row, however
 * many times it is granted (a webhook retried, a button double-clicked). Manual sources need a staff actor with the
 * grant permission and need no payment (I05). Commerce callers (P8) pass their order or subscription as the reference.
 */
final class GrantEntitlement
{
    public function __construct(private readonly AuditLogger $audit, private readonly EntitlementService $service) {}

    public function __invoke(
        User $learner,
        Course $course,
        EntitlementSource $source,
        string $sourceRef = '',
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
        ?User $actor = null,
        ?string $reason = null,
    ): Entitlement {
        if ($source->isManual() && ($actor === null || $actor->getAttribute('status') !== 'active' || ! $actor->can('manual_entitlement.grant'))) {
            throw new AuthorizationException;
        }
        $startsAt ??= now();
        if ($endsAt !== null && $endsAt->lte($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => __('access.ends_before_start')]);
        }

        $keys = ['source_type' => $source->value, 'source_ref' => $sourceRef, 'learner_id' => $learner->getKey(), 'course_id' => $course->getKey()];

        $entitlement = DB::transaction(function () use ($keys, $startsAt, $endsAt, $actor, $reason, $course): Entitlement {
            try {
                $entitlement = Entitlement::query()->firstOrCreate($keys, [
                    'starts_at' => $startsAt, 'ends_at' => $endsAt, 'status' => Entitlement::ACTIVE,
                    'granted_by' => $actor?->getKey(), 'reason' => $reason,
                ]);
            } catch (UniqueConstraintViolationException) {
                $entitlement = Entitlement::query()->where($keys)->firstOrFail(); // a concurrent grant won the race
            }

            if ($entitlement->wasRecentlyCreated && $this->audit->record('entitlement.granted', $course, $actor === null ? null : (int) $actor->getKey(), null, ['learner_id' => $keys['learner_id'], 'source' => $keys['source_type'], 'ref' => $keys['source_ref']]) === null) {
                throw new RuntimeException('Unable to record entitlement audit.');
            }

            return $entitlement;
        });

        $this->service->flush();

        return $entitlement;
    }
}
