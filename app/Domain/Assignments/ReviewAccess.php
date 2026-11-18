<?php

declare(strict_types=1);

namespace App\Domain\Assignments;

use App\Domain\Catalog\Queries\CourseDirectory;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Who may review work (assignment submissions and essay answers) in a course: active staff with the assessment permission
 * who pass the course update gate, which means an instructor with manage scope on the course, or an authorized admin.
 */
final class ReviewAccess
{
    public static function allows(User $viewer, Course $course): bool
    {
        return $viewer->getAttribute('status') === 'active' && $viewer->can('assessment.manage') && Gate::forUser($viewer)->allows('update', $course);
    }

    public static function authorize(User $actor, Course $course): void
    {
        if (! self::allows($actor, $course)) {
            throw new AuthorizationException;
        }
    }

    /** @return list<int> ids of the courses this user may review work in (empty for everyone else) */
    public static function courseIds(User $viewer): array
    {
        if ($viewer->getAttribute('status') !== 'active' || ! $viewer->can('assessment.manage')) {
            return [];
        }

        if (! $viewer->hasRole('super_admin')) {
            return CourseDirectory::for($viewer, manage: true)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        }

        return CourseDirectory::for($viewer)->get()
            ->filter(static fn (Course $course): bool => Gate::forUser($viewer)->allows('update', $course))
            ->map(static fn (Course $course): int => (int) $course->getKey())
            ->values()->all();
    }
}
