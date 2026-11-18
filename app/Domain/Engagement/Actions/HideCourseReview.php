<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Actions;

use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewModeration;
use App\Domain\Engagement\ReviewStatus;
use App\Models\User;

/** Moderator takes a published review down with a written reason; it stays for the audit and is never shown publicly. */
final class HideCourseReview
{
    public function __construct(private readonly ReviewModeration $moderation) {}

    public function __invoke(User $actor, CourseReview $review, ?string $reason = null): CourseReview
    {
        return $this->moderation->transition($actor, $review, ReviewStatus::Hidden, [ReviewStatus::Published], $reason, true);
    }
}
