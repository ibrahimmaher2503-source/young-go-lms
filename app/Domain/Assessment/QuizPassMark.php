<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use App\Models\Course;
use App\Models\Quiz;

/**
 * O-020: the pass mark a quiz is judged by. Order: an explicit mark on the quiz, then the course default
 * (courses.quiz_pass_mark_percent), then the platform default (60%).
 */
final class QuizPassMark
{
    public const int FALLBACK_PERCENT = 60;

    public static function default(): int
    {
        $configured = config('assessment.default_pass_mark_percent');

        return is_numeric($configured) && (int) $configured >= 1 && (int) $configured <= 100 ? (int) $configured : self::FALLBACK_PERCENT;
    }

    /** An out-of-range stored value is ignored, so a bad row can never make every learner pass or fail. */
    public static function effective(?int $explicit, ?int $course = null): int
    {
        foreach ([$explicit, $course] as $candidate) {
            if ($candidate !== null && $candidate >= 1 && $candidate <= 100) {
                return $candidate;
            }
        }

        return self::default();
    }

    /** The mark a quiz inherits when it sets none: the course default, else the platform default. */
    public static function inherited(Quiz $quiz): int
    {
        return self::effective(null, self::courseMark($quiz));
    }

    /** The mark that applies to this quiz now. */
    public static function forQuiz(Quiz $quiz): int
    {
        return self::effective($quiz->pass_mark_percent, self::courseMark($quiz));
    }

    private static function courseMark(Quiz $quiz): ?int
    {
        $course = $quiz->item()->first()?->course()->first();

        return $course instanceof Course && $course->quiz_pass_mark_percent !== null ? (int) $course->quiz_pass_mark_percent : null;
    }
}
