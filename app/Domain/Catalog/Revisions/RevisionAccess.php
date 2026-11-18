<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions;

use App\Domain\Catalog\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Who must go through a revision (YG-GAP-CHG-001/002). A published independent course can be changed only through a
 * revision by anyone who cannot publish it; users who hold the course's `publish` grant change the live course directly
 * and every such change is audited (course.updated_live).
 */
final class RevisionAccess
{
    public static function requiresRevision(User $actor, Course $course): bool
    {
        return $course->status === CourseStatus::Published
            && $course->owner_type === Course::OWNER_INDEPENDENT
            && ! Gate::forUser($actor)->allows('publish', $course);
    }

    /** Live-content writers (wizard, curriculum structure) call this after their own authorization. */
    public static function assertLiveEditable(User $actor, Course $course): void
    {
        if (self::requiresRevision($actor, $course)) {
            throw ValidationException::withMessages(['course' => __('course_revisions.use_revision')]);
        }
    }
}
