<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Actions;

use App\Domain\Assignments\AssignmentRefusedException;
use App\Domain\Assignments\SubmissionRevisions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\EntitlementService;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hands the working revision in. Draft becomes submitted; a returned submission becomes resubmitted on its next
 * revision. The late policy is applied here, on the server clock: not_allowed refuses after the due date, allowed marks
 * the revision late, penalty marks it late and copies the penalty percent (applied later at grading). Empty work is refused.
 */
final class SubmitAssignment
{
    public function __construct(
        private readonly EntitlementService $access,
        private readonly AuditLogger $audit,
        private readonly SendAssignmentNotices $notices,
    ) {}

    public function __invoke(User $learner, CurriculumItem $item): AssignmentSubmission
    {
        $assignment = $item->itemable;
        if (! $assignment instanceof Assignment || $assignment->archived_at !== null || ! $this->access->canAccessItem($learner, $item)->allowed) {
            throw new AssignmentRefusedException(AssignmentRefusedException::NO_ACCESS);
        }

        return DB::transaction(function () use ($learner, $assignment, $item): AssignmentSubmission {
            $submission = AssignmentSubmission::query()->where('assignment_id', $assignment->getKey())->where('learner_id', $learner->getKey())->lockForUpdate()->first();
            if ($submission === null) {
                throw new AssignmentRefusedException(AssignmentRefusedException::NOTHING_TO_SUBMIT);
            }
            if (! SubmissionRevisions::isEditableStatus($submission->status)) {
                throw new AssignmentRefusedException(AssignmentRefusedException::NOT_EDITABLE);
            }

            $resubmitting = $submission->status === AssignmentSubmission::RETURNED;
            if ($resubmitting && SubmissionRevisions::revisionsLeft($assignment, $submission) <= 0) {
                throw new AssignmentRefusedException(AssignmentRefusedException::REVISIONS_EXHAUSTED);
            }

            $revision = SubmissionRevisions::working($submission);
            if ($revision === null || (blank($revision->text_body) && ! $revision->files()->exists())) {
                throw new AssignmentRefusedException(AssignmentRefusedException::NOTHING_TO_SUBMIT);
            }

            $late = $assignment->isPastDue();
            if ($late && $assignment->late_policy === Assignment::LATE_NOT_ALLOWED) {
                throw new AssignmentRefusedException(AssignmentRefusedException::LATE_NOT_ALLOWED);
            }

            $revision->forceFill([
                'submitted_at' => now(), 'is_late' => $late,
                'late_penalty_percent' => $late && $assignment->late_policy === Assignment::LATE_PENALTY ? $assignment->late_penalty_percent : null,
            ])->save();
            $submission->forceFill(['status' => $resubmitting ? AssignmentSubmission::RESUBMITTED : AssignmentSubmission::SUBMITTED, 'current_revision' => $revision->number])->save();

            $event = $resubmitting ? 'assignment.resubmitted' : 'assignment.submitted';
            if ($this->audit->record($event, $assignment, (int) $learner->getKey(), null, ['submission_id' => $submission->getKey(), 'revision' => $revision->number, 'is_late' => $late]) === null) {
                throw new RuntimeException('Unable to record assignment audit.');
            }

            DB::afterCommit(fn () => rescue(fn () => $this->notices->submitted($assignment, $item, (int) $submission->getKey(), $revision->number)));

            return $submission;
        });
    }
}
