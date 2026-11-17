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
    public static function for(?User $user): Builder
    {
        $query = Course::query();

        if ($user === null || ! $user->can('course.view')) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('panel.admin.access')) {
            return $query;
        }

        if (! $user->can('panel.instructor.access')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('instructors', static fn (Builder $instructors): Builder => $instructors->whereKey($user->getKey()));
    }

    /** @return array{active:int, paid:int, free:int, upcoming:int, suspended:int} */
    public static function counts(?User $user): array
    {
        $query = self::for($user);

        return [
            'active' => (clone $query)->where('status', CourseStatus::Published)->count(),
            'paid' => (clone $query)->where('access_mode', Course::ACCESS_PAID)->count(),
            'free' => (clone $query)->where('access_mode', Course::ACCESS_FREE)->count(),
            'upcoming' => (clone $query)->where('status', CourseStatus::Upcoming)->count(),
            'suspended' => (clone $query)->where('status', CourseStatus::Suspended)->count(),
        ];
    }
}
