<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Actions;

use App\Domain\Assessment\Notifications\EssayReviewedNotice;
use App\Domain\Assessment\QuizAccess;
use App\Domain\Assessment\QuizScoring;
use App\Domain\Assignments\ReviewAccess;
use App\Domain\Assignments\ReviewRefusedException;
use App\Domain\Audit\AuditLogger;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Progress\Actions\CompleteAssessmentItem;
use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * A person grades one essay answer of a submitted attempt. Points are 0 up to the question points (from the attempt
 * snapshot). The attempt score, percent and pass state are recomputed by QuizScoring; needs_review clears once every
 * written essay has points. Progression follows the quiz rule: after_submission was already completed at submit, so only
 * require_pass completes here, and only when the attempt now passes (completion is never undone). The learner is notified
 * once, when the review of the attempt first completes; a later regrade is audited only.
 */
final class GradeEssayAnswer
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CompleteAssessmentItem $complete,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function __invoke(User $reviewer, QuizAttempt $attempt, int $questionId, float $points, ?string $feedback = null): QuizAnswer
    {
        $quiz = $attempt->quiz;
        ReviewAccess::authorize($reviewer, QuizAccess::courseOf($quiz));

        $question = collect($attempt->snapshot['questions'])->first(static fn (array $q): bool => (int) $q['id'] === $questionId);
        if ($question === null || $question['type'] !== Question::ESSAY) {
            throw new ReviewRefusedException(ReviewRefusedException::NOT_ESSAY);
        }
        if ($points < 0 || $points > (float) $question['points']) {
            throw new ReviewRefusedException(ReviewRefusedException::SCORE_RANGE, __('review.refused.essay_points_range', ['max' => rtrim(rtrim(number_format((float) $question['points'], 2, '.', ''), '0'), '.')]));
        }

        [$answer, $reviewCompleted, $passed] = DB::transaction(function () use ($reviewer, $attempt, $questionId, $points, $feedback, $question): array {
            $locked = QuizAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();
            $row = QuizAnswer::query()->where('quiz_attempt_id', $locked->getKey())->where('question_id', $questionId)->first();
            if ($locked->status !== QuizAttempt::SUBMITTED || $row === null || $row->answer === null || trim((string) ($row->answer['text'] ?? '')) === '') {
                throw new ReviewRefusedException(ReviewRefusedException::NOT_REVIEWABLE);
            }

            $wasPending = $locked->needs_review;
            $before = ['points' => $row->points_awarded === null ? null : (float) $row->points_awarded, 'attempt_percent' => $locked->percent === null ? null : (float) $locked->percent, 'passed' => $locked->passed];
            $row->forceFill([
                'points_awarded' => round($points, 2), 'is_correct' => $points >= (float) $question['points'], 'feedback' => filled($feedback) ? trim((string) $feedback) : null,
                'graded_by' => $reviewer->getKey(), 'graded_at' => now(),
            ])->save();
            $locked = QuizScoring::apply($locked);

            $after = ['points' => round($points, 2), 'attempt_percent' => (float) $locked->percent, 'passed' => $locked->passed];
            if ($this->audit->record('quiz.essay_graded', $locked->quiz, (int) $reviewer->getKey(), $before, $after, ['attempt_id' => $locked->getKey(), 'question_id' => $questionId]) === null) {
                throw new RuntimeException('Unable to record quiz audit.');
            }

            return [$row, $wasPending && ! $locked->needs_review, $locked->passed];
        });

        $this->afterCommit($attempt->fresh() ?? $attempt, $reviewCompleted, $passed === true);

        return $answer;
    }

    private function afterCommit(QuizAttempt $attempt, bool $reviewCompleted, bool $passed): void
    {
        $learner = $attempt->learner;
        $item = $attempt->quiz->item()->first();
        if (! $learner instanceof User || $item === null) {
            return;
        }

        try {
            if ($passed && (string) ($attempt->settings['progression'] ?? 'after_submission') === 'require_pass') {
                $this->complete->__invoke($learner, $item, 'quiz_passed');
            }
            if ($reviewCompleted) {
                $this->notifications->send($learner, new EssayReviewedNotice($attempt, (int) $item->course_id, (int) $item->getKey()));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
