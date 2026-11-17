<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Contracts;

use App\Models\Course;

/**
 * A module that holds records which make deleting a course unsafe (access grants, progress, orders, attempts)
 * answers here. Modules register their checker under the container tag "course.history"; deleting a course asks all of them.
 */
interface CourseHistoryChecker
{
    /** @return string|null a short reason code (lang key under courses.blocked.*) when this module has history for the course */
    public function blocks(Course $course): ?string;
}
