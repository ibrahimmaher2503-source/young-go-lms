<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Actions;

use App\Domain\Assessment\QuizAnswerWriter;
use App\Domain\Assessment\QuizGrader;
use App\Domain\Assessment\QuizRefusedException;
use App\Domain\Audit\AuditLogger;
use App\Domain\Progress\Actions\CompleteAssessmentItem;
use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Closes an attempt and grades it from the snapshot. A submit after the deadline (plus grace) ignores anything sent with
 * it and uses only what was saved in time, marked as an automatic submit. Submitting twice is harmless. Essays wait for a
 * person (needs_review), and while one waits there is no pass or fail. Progression: the quiz counts as done once submitted
 * (after_submission) or once passed (require_pass), which is what opens the next items in a sequential course.
 */
final class SubmitQuizAttempt
{
    public function __construct(
        private readonly QuizAnswerWriter $writer,
        private readonly AuditLogger $audit,
        private readonly CompleteAssessmentItem $complete,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $answers  final answers sent with the submit
     * @param  bool  $auto  true when the system closes it (time ran out, expiry job)
     */
    public function __invoke(User $learner, QuizAttempt $attempt, ?array $answers = null, bool $auto = false): QuizAttempt
    {
        if ((int) $attempt->learner_id !== (int) $learner->getKey()) {
            throw new QuizRefusedException(QuizRefusedException::NO_ACCESS);
        }

        $done = DB::transaction(function () use ($learner, $attempt, $answers, $auto): QuizAttempt {
            $locked = QuizAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->isOpen()) {
                return $locked; // already submitted: the first submit wins
            }

            $late = $locked->deadline_at !== null && now()->gt($locked->deadline_at->copy()->addSeconds((int) config('assessment.grace_seconds')));
            if (! $auto && ! $late && $answers !== null) {
                $this->writer->apply($locked, $answers);
            }
            $this->grade($locked);
            $locked->update(['status' => QuizAttempt::SUBMITTED, 'submitted_at' => now(), 'submitted_by' => ($auto || $late) ? 'auto' : 'learner']);

            if ($this->audit->record('quiz.attempt_submitted', $locked->quiz, (int) $learner->getKey(), null, ['attempt_no' => $locked->attempt_no, 'by' => $locked->submitted_by]) === null) {
                throw new RuntimeException('Unable to record quiz audit.');
            }

            return $locked;
        });

        $this->progress($learner, $done);

        return $done;
    }

    private function grade(QuizAttempt $attempt): void
    {
        $answers = QuizAnswer::query()->where('quiz_attempt_id', $attempt->getKey())->get()->keyBy('question_id');
        $score = 0.0;
        $total = 0.0;
        $pending = false;

        foreach ($attempt->snapshot['questions'] as $question) {
            $total += (float) $question['points'];
            $row = $answers->get((int) $question['id']);
            $given = $row === null ? null : $row->answer;

            if ($question['type'] === Question::ESSAY && is_array($given) && trim((string) ($given['text'] ?? '')) !== '') {
                $pending = true; // a written essay waits for a person
                $result = ['is_correct' => null, 'points' => null];
            } elseif ($question['type'] === Question::ESSAY) {
                $result = ['is_correct' => false, 'points' => 0.0]; // an empty essay has nothing to review
            } else {
                $result = QuizGrader::grade($question, $given);
            }

            $score += (float) ($result['points'] ?? 0);
            QuizAnswer::query()->updateOrCreate(
                ['quiz_attempt_id' => $attempt->getKey(), 'question_id' => (int) $question['id']],
                ['question_version' => (int) $question['version'], 'answer' => $given, 'is_correct' => $result['is_correct'], 'points_awarded' => $result['points']],
            );
        }

        $percent = $total > 0 ? round($score / $total * 100, 2) : 0.0;
        $mark = $attempt->settings['pass_mark_percent'] ?? null;
        $attempt->forceFill([
            'score' => round($score, 2), 'total' => round($total, 2), 'percent' => $percent, 'needs_review' => $pending,
            'passed' => ($mark === null || $pending) ? null : $percent >= (float) $mark,
        ])->save();
    }

    /** Called after the transaction, so a failure here never loses a submitted attempt. */
    private function progress(User $learner, QuizAttempt $attempt): void
    {
        $item = $attempt->quiz->item()->first();
        if ($item === null) {
            return;
        }
        $progression = (string) ($attempt->settings['progression'] ?? 'after_submission');
        if ($progression === 'after_submission') {
            $this->complete->__invoke($learner, $item, 'quiz_submitted');
        } elseif ($attempt->passed === true) {
            $this->complete->__invoke($learner, $item, 'quiz_passed');
        }
    }
}
