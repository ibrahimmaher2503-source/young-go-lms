<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment counts per course for the reports module. It lives in the Entitlements module because only that module reads the
 * entitlements table (I01). Counts are distinct learners, so a renewal, a second source or a retry never inflates them.
 */
final class EnrollmentReport
{
    /**
     * @param  list<int>  $courseIds
     * @return array<int, array{new: int, active: int, total: int}> total = learners who ever had access, new = learners whose first access started in the range, active = learners with access now
     */
    public static function forCourses(array $courseIds, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($courseIds === []) {
            return [];
        }

        $now = now()->utc()->format('Y-m-d H:i:s');
        $perLearner = Entitlement::query()->whereIn('course_id', $courseIds)->groupBy('course_id', 'learner_id')
            ->selectRaw('course_id, learner_id, min(starts_at) as first_at, max(case when status = ? and starts_at <= ? and (ends_at is null or ends_at > ?) then 1 else 0 end) as is_active', [Entitlement::ACTIVE, $now, $now]);

        $rows = DB::query()->fromSub($perLearner->toBase(), 'per_learner')->groupBy('course_id')
            ->selectRaw('course_id, count(*) as total, sum(case when first_at >= ? and first_at <= ? then 1 else 0 end) as new_count, sum(is_active) as active_count', [$from->clone()->utc()->format('Y-m-d H:i:s'), $to->clone()->utc()->format('Y-m-d H:i:s')])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->course_id] = ['new' => (int) $row->new_count, 'active' => (int) $row->active_count, 'total' => (int) $row->total];
        }

        return $out;
    }
}
