<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Actions;

use App\Models\QuizAttempt;

/**
 * Submits every open attempt whose time is over (deadline plus grace), using the answers saved so far. Runs on a schedule
 * so an abandoned timed attempt is graded and its slot counted even if the learner never comes back.
 */
final class ExpireQuizAttempts
{
    public function __construct(private readonly SubmitQuizAttempt $submit) {}

    /** @return int attempts closed in this run */
    public function __invoke(): int
    {
        $closed = 0;
        $cutoff = now()->subSeconds((int) config('assessment.grace_seconds'));

        QuizAttempt::query()->where('status', QuizAttempt::IN_PROGRESS)->whereNotNull('deadline_at')->where('deadline_at', '<', $cutoff)->with(['learner', 'quiz'])
            ->chunkById(100, function ($attempts) use (&$closed): void {
                foreach ($attempts as $attempt) {
                    try {
                        $this->submit->__invoke($attempt->learner, $attempt, null, true);
                        $closed++;
                    } catch (\Throwable $exception) {
                        report($exception); // one broken attempt must not stop the rest
                    }
                }
            });

        return $closed;
    }
}
