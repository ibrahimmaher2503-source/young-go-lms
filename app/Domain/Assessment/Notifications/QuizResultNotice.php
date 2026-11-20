<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Notifications;

use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationTemplates;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * Tells a learner their quiz result is ready (on immediate auto-graded submit or manual result release).
 * Shares the per-attempt dedupe key with EssayReviewedNotice so an attempt notifies once.
 */
final class QuizResultNotice extends LocalizedNotification
{
    public function __construct(private readonly QuizAttempt $attempt, private readonly int $courseId, private readonly int $itemId) {}

    public function event(): string
    {
        return 'quiz.result';
    }

    public function dedupeKey(): string
    {
        return 'quiz-review:'.$this->attempt->getKey();
    }

    protected function title(User $recipient): string
    {
        $values = ['title' => $this->quizTitle()];

        return NotificationTemplates::render('quiz.result', 'title', $values, (string) __('review.notice.quiz_result.title', $values));
    }

    protected function body(User $recipient): string
    {
        $values = ['title' => $this->quizTitle()];

        return NotificationTemplates::render('quiz.result', 'body', $values, (string) __('review.notice.quiz_result.body', $values));
    }

    protected function url(User $recipient): string
    {
        return route('quiz.attempt', ['course' => $this->courseId, 'item' => $this->itemId, 'attempt' => $this->attempt->getKey()]);
    }

    private function quizTitle(): string
    {
        $quiz = $this->attempt->quiz;
        $translated = (string) $quiz->getTranslation('title', app()->getLocale());

        return $translated !== '' ? $translated : (string) $quiz->title;
    }
}
