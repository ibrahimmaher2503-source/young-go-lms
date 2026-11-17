<?php

declare(strict_types=1);

namespace App\Domain\Cohorts;

use App\Domain\Catalog\Contracts\CourseHistoryChecker;
use App\Models\Course;

/** A course that ever had a cohort with a member is history. */
final class CohortHistory implements CourseHistoryChecker
{
    public function blocks(Course $course): ?string
    {
        return CohortMember::query()->whereIn('cohort_id', Cohort::query()->where('course_id', $course->getKey())->select('id'))->exists() ? 'cohorts' : null;
    }
}
