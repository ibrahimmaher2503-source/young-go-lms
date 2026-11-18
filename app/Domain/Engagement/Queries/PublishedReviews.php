<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Queries;

use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewStatus;
use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;

/** What the public course page may show: published reviews only, newest first, with the display name snapshot and nothing else about the author. */
final class PublishedReviews
{
    /** @return Collection<int, CourseReview> */
    public function forCourse(Course $course, ?int $limit = null): Collection
    {
        return CourseReview::query()
            ->select(['id', 'course_id', 'rating', 'body', 'author_name', 'created_at'])
            ->where('course_id', $course->getKey())
            ->where('status', ReviewStatus::Published->value)
            ->latest('created_at')->latest('id')
            ->limit($limit ?? max(1, (int) config('engagement.reviews.public_per_page')))
            ->get();
    }
}
