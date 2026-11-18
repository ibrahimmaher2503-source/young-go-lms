<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class CourseDirectory
{
    /** @return Builder<Course> */
    public static function for(?User $user, bool $manage = false): Builder
    {
        $query = Course::query();

        if ($user === null || ! $user->can('course.view') || ($manage && ! $user->can('course.update'))) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('panel.admin.access')) {
            return $query;
        }

        if (! $user->can('panel.instructor.access')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('instructors', static fn (Builder $instructors): Builder => $instructors
            ->whereKey($user->getKey())
            ->when($manage, static fn (Builder $assigned): Builder => $assigned->where('course_instructors.scope', Course::INSTRUCTOR_SCOPE_MANAGE)));
    }

    /** @return array{active:int, paid:int, free:int, upcoming:int, suspended:int} */
    public static function counts(?User $user): array
    {
        $counts = self::for($user)->toBase()->selectRaw(
            'count(case when status = ? then 1 end) as active,
             count(case when access_mode = ? then 1 end) as paid,
             count(case when access_mode = ? then 1 end) as free,
             count(case when status = ? then 1 end) as upcoming,
             count(case when status = ? then 1 end) as suspended',
            [CourseStatus::Published->value, Course::ACCESS_PAID, Course::ACCESS_FREE, CourseStatus::Upcoming->value, CourseStatus::Suspended->value],
        )->first();

        return [
            'active' => (int) ($counts->active ?? 0),
            'paid' => (int) ($counts->paid ?? 0),
            'free' => (int) ($counts->free ?? 0),
            'upcoming' => (int) ($counts->upcoming ?? 0),
            'suspended' => (int) ($counts->suspended ?? 0),
        ];
    }
}
