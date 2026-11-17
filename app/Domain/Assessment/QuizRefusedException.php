<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use RuntimeException;

/** Why a learner action on a quiz was refused. NO_ACCESS never says whether the quiz exists; the others are shown to someone allowed to see it. */
final class QuizRefusedException extends RuntimeException
{
    public const string NO_ACCESS = 'no_access';

    public const string ATTEMPTS_EXHAUSTED = 'attempts_exhausted';

    public const string NO_QUESTIONS = 'no_questions';

    public const string EXPIRED = 'expired';

    public const string NOT_OPEN = 'not_open';

    public const string INVALID_ANSWER = 'invalid_answer';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('quiz.attempt.refused.'.$reason));
    }
}
