<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Actions;

use App\Domain\Assessment\QuizAnswerWriter;
use App\Domain\Assessment\QuizRefusedException;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * Saves the learner answers of an open attempt (autosave). Nothing is accepted after the deadline (plus a small network
 * grace): the attempt is then submitted with what was saved and the learner is told time was up. Saving never grades.
 */
final class SaveQuizAnswers
{
    public function __construct(private readonly SubmitQuizAttempt $submit, private readonly QuizAnswerWriter $writer) {}

    /**
     * @param  array<array-key, mixed>  $answers  question id => {selected: [keys]} or {text: string}
     * @return int how many answers were stored
     */
    public function __invoke(User $learner, QuizAttempt $attempt, array $answers): int
    {
        if ((int) $attempt->learner_id !== (int) $learner->getKey()) {
            throw new QuizRefusedException(QuizRefusedException::NO_ACCESS);
        }
        $fresh = $attempt->refresh();
        if (! $fresh->isOpen()) {
            throw new QuizRefusedException(QuizRefusedException::NOT_OPEN);
        }
        if ($fresh->deadline_at !== null && now()->gt($fresh->deadline_at->copy()->addSeconds((int) config('assessment.grace_seconds')))) {
            $this->submit->__invoke($learner, $fresh, null, true);

            throw new QuizRefusedException(QuizRefusedException::EXPIRED);
        }

        return $this->writer->apply($fresh, $answers);
    }
}
