<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

/** O-020: the pass mark a quiz is judged by. An explicit mark on the quiz wins; otherwise the platform default (60%). */
final class QuizPassMark
{
    public const int FALLBACK_PERCENT = 60;

    public static function default(): int
    {
        $configured = config('assessment.default_pass_mark_percent');

        return is_numeric($configured) && (int) $configured >= 1 && (int) $configured <= 100 ? (int) $configured : self::FALLBACK_PERCENT;
    }

    public static function effective(?int $explicit): int
    {
        return $explicit ?? self::default();
    }
}
