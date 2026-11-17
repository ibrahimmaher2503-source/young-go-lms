<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/** Takes a course out of sale and out of learners' hands while keeping every record. Safe to repeat. */
final class ArchiveCourse
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Course $course): Course
    {
        Gate::forUser($actor)->authorize('archive', $course);

        if ($course->status === CourseStatus::Archived) {
            return $course;
        }

        return DB::transaction(function () use ($actor, $course): Course {
            $before = $course->status->value;
            $course->forceFill(['status' => CourseStatus::Archived, 'featured' => false])->save();
            if ($this->audit->record('course.archived', $course, (int) $actor->getKey(), ['status' => $before], ['status' => 'archived']) === null) {
                throw new RuntimeException('Unable to record course audit.');
            }

            return $course;
        });
    }
}
