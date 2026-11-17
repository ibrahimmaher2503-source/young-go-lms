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
        $entitlements = Entitlement::query()->where('course_id', $course->getKey());

        return User::query()
            ->whereIn('users.id', (clone $entitlements)->select('learner_id'))
            ->addSelect([
                'access_status' => Entitlement::query()->selectRaw("case when sum(case when status = 'active' and starts_at <= ? and (ends_at is null or ends_at > ?) then 1 else 0 end) > 0 then 'active' else 'ended' end", [now(), now()])
                    ->whereColumn('entitlements.learner_id', 'users.id')->where('entitlements.course_id', $course->getKey()),
                'enrolled_at' => Entitlement::query()->selectRaw('min(starts_at)')->whereColumn('entitlements.learner_id', 'users.id')->where('entitlements.course_id', $course->getKey()),
            ]);
    }

    public static function count(Course $course): int
    {
        return Entitlement::query()->where('course_id', $course->getKey())->distinct()->count('learner_id');
    }
}
