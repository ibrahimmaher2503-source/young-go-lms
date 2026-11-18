<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Engagement\Wishlist;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Reads of a learner's saved courses. Only published courses are ever listed. */
final class WishlistQuery
{
    /** @return list<int> */
    public function savedIds(User $learner): array
    {
        return Wishlist::query()->where('user_id', $learner->getKey())->pluck('course_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /** @return Builder<Course> */
    public function courses(User $learner): Builder
    {
        return Course::query()
            ->where('courses.status', CourseStatus::Published)
            ->whereIn('courses.id', Wishlist::query()->where('user_id', $learner->getKey())->select('course_id'))
            ->with(['category', 'instructors'])
            ->orderByDesc(Wishlist::query()->select('id')->whereColumn('wishlists.course_id', 'courses.id')->where('user_id', $learner->getKey())->limit(1));
    }
}
