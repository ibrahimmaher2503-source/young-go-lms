<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use App\Models\QuizAttempt;
use App\Models\User;

/**
 * The AI hook for attempts (T-P6-02): while a learner has an open attempt that is still inside its time, the quiz guard
 * is active for that learner. The assistant pipeline asks here before answering and must refuse questions of that quiz
 * (the open graded questions come from QuizGuardQuestions). This class only answers the question; it never calls the assistant.
 */
final class QuizGuard
{
    public static function isActive(User $learner): bool
    {
        return self::openAttempt($learner) !== null;
    }

    public static function openAttempt(User $learner): ?QuizAttempt
    {
        return QuizAttempt::query()->where('learner_id', $learner->getKey())->where('status', QuizAttempt::IN_PROGRESS)
            ->where(fn ($query) => $query->whereNull('deadline_at')->orWhere('deadline_at', '>', now()->subSeconds((int) config('assessment.grace_seconds'))))
            ->latest('started_at')->first();
    }
}
