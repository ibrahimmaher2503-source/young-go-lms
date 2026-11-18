<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Notifications;

use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewStatus;
use App\Domain\Notifications\LocalizedNotification;
use App\Models\User;

/**
 * Tells a learner what moderation decided about their review. A rejection carries the written reason; a hidden review
 * only says it is no longer shown. One message per decision.
 */
final class ReviewDecisionNotice extends LocalizedNotification
{
    public function __construct(private readonly CourseReview $review) {}

    public function dedupeKey(): string
    {
        return 'review-decision:'.$this->review->getKey().':'.$this->review->status->value.':'.($this->review->moderated_at?->getTimestamp() ?? 0);
    }

    protected function title(User $recipient): string
    {
        return __('engagement.notice.'.$this->review->status->value.'.title');
    }

    protected function body(User $recipient): string
    {
        $course = (string) $this->review->course->title;
        $reason = $this->review->status === ReviewStatus::Rejected ? (string) $this->review->moderation_reason : '';

        return __('engagement.notice.'.$this->review->status->value.'.body', ['course' => $course, 'reason' => $reason]);
    }

    protected function url(User $recipient): ?string
    {
        $course = $this->review->course;

        return $course === null ? null : route('catalog.show', ['course' => $course->getKey()]).'#reviews';
    }
}
