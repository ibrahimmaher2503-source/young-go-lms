<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;

/**
 * Turns the awarded points of an attempt into its score, percent, pass state and review flag. Used when an attempt is
 * submitted and again when a person grades an essay, so both always agree. While an essay waits there is no pass or fail.
 */
final class QuizScoring
{
    /** An essay answer a person still has to grade: written, and no points yet. */
    public static function pendingEssays(QuizAttempt $attempt): int
    {
        $essayIds = [];
        foreach ($attempt->snapshot['questions'] as $question) {
            if ($question['type'] === Question::ESSAY) {
                $essayIds[] = (int) $question['id'];
            }
        }

        return $essayIds === [] ? 0 : QuizAnswer::query()->where('quiz_attempt_id', $attempt->getKey())->whereIn('question_id', $essayIds)->whereNull('points_awarded')->count();
    }

    /** Recomputes and saves score, total, percent, needs_review and passed from the stored answers. */
    public static function apply(QuizAttempt $attempt): QuizAttempt
    {
        $awarded = QuizAnswer::query()->where('quiz_attempt_id', $attempt->getKey())->pluck('points_awarded', 'question_id');
        $score = 0.0;
        $total = 0.0;
        foreach ($attempt->snapshot['questions'] as $question) {
            $total += (float) $question['points'];
            $score += (float) ($awarded->get((int) $question['id']) ?? 0);
        }

        $pending = self::pendingEssays($attempt) > 0;
        $percent = $total > 0 ? round($score / $total * 100, 2) : 0.0;
        $mark = QuizPassMark::effective(is_numeric($attempt->settings['pass_mark_percent'] ?? null) ? (int) $attempt->settings['pass_mark_percent'] : null); // O-020
        $attempt->forceFill([
            'score' => round($score, 2), 'total' => round($total, 2), 'percent' => $percent, 'needs_review' => $pending,
            'passed' => $pending ? null : $percent >= (float) $mark,
        ])->save();

        return $attempt;
    }
}
