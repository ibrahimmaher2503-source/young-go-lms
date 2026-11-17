<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Actions;

use App\Domain\Assignments\AssignmentAccess;
use App\Domain\Assignments\Notifications\SubmissionDecisionNotice;
use App\Domain\Assignments\ReviewAccess;
use App\Domain\Assignments\ReviewRefusedException;
use App\Domain\Assignments\SubmissionReview;
use App\Domain\Assignments\SubmissionRevisions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Completion\CompletionTrigger;
use App\Domain\Notifications\NotificationDispatcher;
use App\Models\AssignmentSubmission;
use App\Models\SubmissionDecision;
use App\Models\SubmissionFeedback;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * A reviewer decision on the current revision of a submission: grade, accept, reject or ask for a revision. The late
 * penalty copied onto the revision at submit time is applied to the score here. Every decision is a new row, is audited
 * with the status and score before and after, and notifies the learner once (the dedupe key is the decision id).
 * Authorization is checked here, at execution time: assessment permission plus the course update gate.
 */
final class DecideSubmission
{
    public function __construct(private readonly AuditLogger $audit, private readonly NotificationDispatcher $notifications) {}

    public function __invoke(User $reviewer, AssignmentSubmission $submission, string $decision, ?float $score = null, ?string $feedback = null): SubmissionDecision
    {
        $assignment = $submission->assignment;
        ReviewAccess::authorize($reviewer, AssignmentAccess::courseOf($assignment));

        $feedback = trim((string) $feedback);
        if (! in_array($decision, SubmissionDecision::DECISIONS, true)) {
            throw new ReviewRefusedException(ReviewRefusedException::NOT_DECIDABLE);
        }
        if ($score !== null && ($score < 0 || $score > 100)) {
            throw new ReviewRefusedException(ReviewRefusedException::SCORE_RANGE);
        }
        if ($decision === SubmissionDecision::GRADED && $score === null) {
            throw new ReviewRefusedException(ReviewRefusedException::SCORE_NEEDED);
        }
        if (in_array($decision, [SubmissionDecision::REJECTED, SubmissionDecision::REVISION_REQUESTED], true) && $feedback === '') {
            throw new ReviewRefusedException(ReviewRefusedException::FEEDBACK_NEEDED);
        }
        if ($decision === SubmissionDecision::REVISION_REQUESTED) {
            $score = null; // a score belongs to a decision on the work, not to a request for changes
        }

        $made = DB::transaction(function () use ($reviewer, $submission, $decision, $score, $feedback, $assignment): SubmissionDecision {
            $locked = AssignmentSubmission::query()->whereKey($submission->getKey())->lockForUpdate()->firstOrFail();
            if (! SubmissionReview::canDecide($locked->status)) {
                throw new ReviewRefusedException(ReviewRefusedException::NOT_DECIDABLE);
            }
            if ($decision === SubmissionDecision::REVISION_REQUESTED && SubmissionRevisions::revisionsLeft($assignment, $locked) <= 0) {
                throw new ReviewRefusedException(ReviewRefusedException::NO_REVISIONS_LEFT);
            }

            $revision = $locked->currentRevision();
            $before = ['status' => $locked->status, 'score' => $locked->decisions()->latest('id')->first()?->final_score];
            $penalty = $revision?->is_late === true ? $revision->late_penalty_percent : null;
            $final = $score === null ? null : SubmissionReview::finalScore($score, $penalty);

            $made = SubmissionDecision::query()->create([
                'submission_id' => $locked->getKey(), 'revision_number' => $locked->current_revision, 'decision' => $decision, 'reviewer_id' => $reviewer->getKey(),
                'score' => $score, 'late_penalty_percent' => $score === null ? null : $penalty, 'final_score' => $final,
            ]);
            if ($feedback !== '') {
                SubmissionFeedback::query()->create([
                    'submission_id' => $locked->getKey(), 'revision_number' => $locked->current_revision, 'decision_id' => $made->getKey(), 'author_id' => $reviewer->getKey(), 'body' => $feedback,
                ]);
            }
            $locked->forceFill(['status' => SubmissionReview::statusFor($decision)])->save();

            $after = ['status' => $locked->status, 'score' => $final, 'raw_score' => $score, 'late_penalty_percent' => $score === null ? null : $penalty];
            if ($this->audit->record('assignment.'.$decision, $assignment, (int) $reviewer->getKey(), $before, $after, ['submission_id' => $locked->getKey(), 'decision_id' => $made->getKey(), 'revision' => $locked->current_revision]) === null) {
                throw new RuntimeException('Unable to record review audit.');
            }

            return $made;
        });

        $this->notify($submission, $made);
        $item = $assignment->item()->first();
        if ($item !== null) {
            CompletionTrigger::changed((int) $submission->learner_id, (int) $item->course_id);
        }

        return $made;
    }

    /** After the transaction: a failing mail never undoes a recorded decision. */
    private function notify(AssignmentSubmission $submission, SubmissionDecision $decision): void
    {
        try {
            $learner = $submission->learner;
            $item = $submission->assignment->item()->first();
            if ($learner instanceof User && $item !== null) {
                $this->notifications->send($learner, new SubmissionDecisionNotice($decision, (int) $item->course_id, (int) $item->getKey(), (string) $submission->assignment->title));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
