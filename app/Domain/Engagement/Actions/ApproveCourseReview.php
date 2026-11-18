<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Actions;

use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewModeration;
use App\Domain\Engagement\ReviewStatus;
use App\Models\User;

/** Moderator publishes a pending review or restores a hidden one; approving a published review is a no-op. */
final class ApproveCourseReview
{
    public function __construct(private readonly ReviewModeration $moderation) {}

    public function __invoke(User $actor, CourseReview $review, ?string $reason = null): CourseReview
    {
        return $this->moderation->transition($actor, $review, ReviewStatus::Published, [ReviewStatus::Pending, ReviewStatus::Hidden], $reason, false);
    }
}
