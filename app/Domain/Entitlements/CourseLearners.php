<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The learners of one course, for the people who run it. It lives in the Entitlements module because only that module
 * reads the entitlements table (I01); the instructor screen asks here and never sees another course's learners.
 */
final class CourseLearners
{
    /** @return Builder<User> learners who have (or had) access to this course, with access state and dates */
    public static function query(Course $course): Builder
    {
        $now = now();
        $entitlements = Entitlement::query()->where('course_id', $course->getKey())
            ->select('learner_id')
            ->selectRaw("case when max(case when status = ? and starts_at <= ? and (ends_at is null or ends_at > ?) then 1 else 0 end) = 1 then 'active' else 'ended' end as access_status", [Entitlement::ACTIVE, $now, $now])
            ->selectRaw('min(starts_at) as enrolled_at')
            ->groupBy('learner_id');

        return User::query()
            ->joinSub($entitlements, 'course_access', 'course_access.learner_id', '=', 'users.id')
            ->select('users.*', 'course_access.access_status', 'course_access.enrolled_at');
    }

    public static function count(Course $course): int
    {
        return Entitlement::query()->where('course_id', $course->getKey())->distinct()->count('learner_id');
    }

    /** @return Builder<Entitlement> Correlated count for a course directory row. */
    public static function countForDirectory(): Builder
    {
        return Entitlement::query()->selectRaw('count(distinct learner_id)')
            ->whereColumn('entitlements.course_id', 'courses.id');
    }
}
