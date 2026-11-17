<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Notifications;

use App\Domain\Notifications\LocalizedNotification;
use App\Models\QuizAttempt;
use App\Models\User;

/** Tells a learner their written answers were graded. One message per attempt: a later regrade does not send another. */
final class EssayReviewedNotice extends LocalizedNotification
{
    public function __construct(private readonly QuizAttempt $attempt, private readonly int $courseId, private readonly int $itemId) {}

    public function dedupeKey(): string
    {
        return 'quiz-review:'.$this->attempt->getKey();
    }

    protected function title(User $recipient): string
    {
        return __('review.notice.essay_reviewed.title', ['title' => (string) $this->attempt->quiz->title]);
    }

    protected function body(User $recipient): string
    {
        return __('review.notice.essay_reviewed.body', ['title' => (string) $this->attempt->quiz->title]);
    }

    protected function url(User $recipient): string
    {
        return route('quiz.attempt', ['course' => $this->courseId, 'item' => $this->itemId, 'attempt' => $this->attempt->getKey()]);
    }
}
