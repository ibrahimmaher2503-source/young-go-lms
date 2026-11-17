<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use App\Domain\Catalog\Contracts\CourseHistoryChecker;
use App\Models\Course;

/** A course that anyone ever had access to (current, ended or revoked) is history and is archived, never deleted. */
final class EntitlementHistory implements CourseHistoryChecker
{
    public function blocks(Course $course): ?string
    {
        return Entitlement::query()->where('course_id', $course->getKey())->exists() ? 'entitlements' : null;
    }
}
