<?php

declare(strict_types=1);

namespace App\Domain\Engagement;

use App\Models\Course;
use Illuminate\Support\Facades\Cache;

/**
 * Rating aggregate over published reviews only. Cached per course and dropped by every Action that changes a review's
 * visibility, so a pending or hidden review never moves the number.
 */
final class ReviewSummary
{
    /** @return array{average: float, count: int} */
    public static function for(Course $course): array
    {
        /** @var array{average: float, count: int} */
        return Cache::remember(self::key((int) $course->getKey()), max(60, (int) config('engagement.reviews.rating_cache_seconds', 86400)), static function () use ($course): array {
            $row = CourseReview::query()->where('course_id', $course->getKey())->where('status', ReviewStatus::Published->value)
                ->selectRaw('count(*) as total, avg(rating) as average')->first();
            $count = (int) ($row->getAttribute('total') ?? 0);

            return ['average' => $count === 0 ? 0.0 : round((float) $row->getAttribute('average'), 1), 'count' => $count];
        });
    }

    public static function forget(int $courseId): void
    {
        Cache::forget(self::key($courseId));
    }

    private static function key(int $courseId): string
    {
        return 'engagement:rating:'.$courseId;
    }
}
