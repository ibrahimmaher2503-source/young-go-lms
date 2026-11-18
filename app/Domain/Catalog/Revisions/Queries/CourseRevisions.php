<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Queries;

use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Models\Course;
use App\Models\CourseRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Read side of revisions: the open revision of a course, its recent history (older ones are out of scope) and the reviewers' queue. */
final class CourseRevisions
{
    public const int HISTORY_LIMIT = 10;

    public static function open(Course $course): ?CourseRevision
    {
        return CourseRevision::query()->where('course_id', $course->getKey())->whereIn('status', [CourseRevisionStatus::Draft->value, CourseRevisionStatus::Pending->value])->with('author')->first();
    }

    /** The latest rejected revision, offered as the starting point of the next one. */
    public static function lastRejected(Course $course): ?CourseRevision
    {
        $latest = CourseRevision::query()->where('course_id', $course->getKey())->orderByDesc('number')->first();

        return $latest?->status === CourseRevisionStatus::Rejected ? $latest : null;
    }

    /** @return Collection<int, CourseRevision> */
    public static function history(Course $course): Collection
    {
        return CourseRevision::query()->where('course_id', $course->getKey())->with(['author', 'reviewer'])->orderByDesc('number')->limit(self::HISTORY_LIMIT)->get();
    }

    /** @return Builder<CourseRevision> revisions the user may see in the queue: all for staff who can publish, none otherwise */
    public static function queue(?User $user): Builder
    {
        $query = CourseRevision::query()->with(['course', 'author', 'reviewer']);

        return $user !== null && $user->can('course.publish') && $user->can('panel.admin.access') ? $query : $query->whereRaw('1 = 0');
    }

    public static function pendingCount(?User $user): int
    {
        return self::queue($user)->where('status', CourseRevisionStatus::Pending->value)->count();
    }
}
