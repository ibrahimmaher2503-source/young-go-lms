<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Queries;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

/** What a learner sees on a quiz page before and between attempts: the rules, how many attempts are left, and past attempts. */
final class QuizOverview
{
    /**
     * @return array{quiz: Quiz, questions: int, total_marks: float, open: QuizAttempt|null, attempts: Collection<int, QuizAttempt>, used: int, left: int|null, can_start: bool}
     */
    public static function for(User $learner, Quiz $quiz): array
    {
        $attempts = QuizAttempt::query()->where('quiz_id', $quiz->getKey())->where('learner_id', $learner->getKey())->orderBy('attempt_no')->get();
        $open = $attempts->first(fn (QuizAttempt $attempt): bool => $attempt->isOpen());
        $used = $attempts->count();
        $left = $quiz->max_attempts === null ? null : max(0, $quiz->max_attempts - $used);
        $active = Question::query()->where('quiz_id', $quiz->getKey())->where('active', true);

        return [
            'quiz' => $quiz, 'questions' => (clone $active)->count(), 'total_marks' => (float) (clone $active)->sum('points'),
            'open' => $open, 'attempts' => $attempts, 'used' => $used, 'left' => $left,
            'can_start' => $open !== null || ($left === null || $left > 0),
        ];
    }
}
