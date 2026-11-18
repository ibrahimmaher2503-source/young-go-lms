<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The AI hook for attempts (T-P6-02): while a learner has an open attempt that is still inside its time, the quiz guard
 * is active for that learner. The assistant pipeline asks here before answering and must refuse questions of that quiz
 * (the open graded questions come from QuizGuardQuestions). This class only answers the question; it never calls the assistant.
 */
final class QuizGuard
{
    public static function isActive(User $learner): bool
    {
        return self::query($learner)->exists();
    }

    /** @return Collection<int, QuizAttempt> */
    public static function openAttempts(User $learner, int $courseId): Collection
    {
        return self::query($learner)->whereHas('quiz.item', fn ($item) => $item->where('course_id', $courseId))
            ->with('quiz')->get();
    }

    /** @return Builder<QuizAttempt> */
    private static function query(User $learner): Builder
    {
        return QuizAttempt::query()->where('learner_id', $learner->getKey())->where('status', QuizAttempt::IN_PROGRESS)
            ->where(fn ($query) => $query->whereNull('deadline_at')->orWhere('deadline_at', '>=', now()->subSeconds((int) config('assessment.grace_seconds'))));
    }
}
