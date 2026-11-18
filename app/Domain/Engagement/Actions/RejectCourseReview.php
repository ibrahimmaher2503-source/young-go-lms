<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Actions;

use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewModeration;
use App\Domain\Engagement\ReviewStatus;
use App\Models\User;

/** Moderator rejects a pending review with a written reason the learner sees; the learner may edit and resubmit. */
final class RejectCourseReview
{
    public function __construct(private readonly ReviewModeration $moderation) {}

    public function __invoke(User $actor, CourseReview $review, ?string $reason = null): CourseReview
    {
        return $this->moderation->transition($actor, $review, ReviewStatus::Rejected, [ReviewStatus::Pending], $reason, true);
    }
}
