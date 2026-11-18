<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Queries;

use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewStatus;

/** Counts for the moderation queue header. */
final class ReviewModerationQueue
{
    /** @return array{pending: int, published: int, hidden: int} */
    public static function summary(): array
    {
        $count = static fn (ReviewStatus $status): int => CourseReview::query()->where('status', $status->value)->count();

        return ['pending' => $count(ReviewStatus::Pending), 'published' => $count(ReviewStatus::Published), 'hidden' => $count(ReviewStatus::Hidden) + $count(ReviewStatus::Rejected)];
    }
}
