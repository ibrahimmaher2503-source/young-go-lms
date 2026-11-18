<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Every access record one learner has, with its source and the live decision, for staff screens (Learner 360, T-P9-10).
 * Only the Entitlements module reads that table (I01). The staff-internal `reason` of a record is never returned, and the
 * source reference only when the caller is allowed to see it.
 */
final class LearnerAccessRows
{
    public function __construct(private readonly EntitlementService $access) {}

    /**
     * @return list<array{course_id: int, course: string, source: string, reference: string|null, starts_at: Carbon, ends_at: Carbon|null, state: string, allowed_now: bool, decision: string|null}>
     */
    public function for(User $learner, bool $withReference, int $limit): array
    {
        $rows = [];
        $decisions = [];

        foreach (Entitlement::query()->where('learner_id', $learner->getKey())->with('course')->orderByDesc('starts_at')->limit($limit)->get() as $record) {
            $course = $record->course;
            if ($course === null) {
                continue;
            }

            $decision = $decisions[$course->getKey()] ??= $this->access->canAccessCourse($learner, $course);

            $rows[] = [
                'course_id' => (int) $course->getKey(),
                'course' => (string) $course->title,
                'source' => $record->source_type->value,
                'reference' => $withReference ? $record->source_ref : null,
                'starts_at' => $record->starts_at,
                'ends_at' => $record->ends_at,
                'state' => $record->accessState(),
                'allowed_now' => $decision->allowed,
                'decision' => $decision->message(),
            ];
        }

        return $rows;
    }
}
