<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The courses one learner has (or had) access to. This lives in the Entitlements module because only that module may
 * read the entitlements table (I01); the dashboard and "my courses" ask here.
 */
final class LearnerCourses
{
    /**
     * One row per course, with the best entitlement for it: a current one wins over an ended one.
     *
     * @return Collection<int, array{course: Course, access: string, ends_at: Carbon|null, source: string}>
     */
    public static function for(User $learner): Collection
    {
        $rows = Entitlement::query()->where('learner_id', $learner->getKey())->with('course')->orderByDesc('starts_at')->get();
        app(EntitlementService::class)->primeLearnerCourses($learner, $rows);
        $byCourse = [];

        foreach ($rows as $entitlement) {
            $course = $entitlement->course;
            if ($course === null) {
                continue;
            }
            $access = $entitlement->isCurrent() ? 'active' : 'ended';
            $current = $byCourse[$entitlement->course_id] ?? null;
            if ($current === null || ($current['access'] === 'ended' && $access === 'active')) {
                $byCourse[$entitlement->course_id] = [
                    'course' => $course, 'access' => $access, 'ends_at' => $entitlement->ends_at, 'source' => $entitlement->source_type->value,
                ];
            }
        }

        return collect(array_values($byCourse))->sortBy(fn (array $row): string => strtolower((string) $row['course']->title))->values();
    }
}
