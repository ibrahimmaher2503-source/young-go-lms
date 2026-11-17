<?php

declare(strict_types=1);

namespace App\Domain\Assignments;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\SubmissionRevision;

/** Which revision a learner is working on, and the resubmission arithmetic. One place so actions and pages agree. */
final class SubmissionRevisions
{
    /** A learner may edit while the work is a draft, or after a reviewer returned it. */
    public static function isEditableStatus(string $status): bool
    {
        return in_array($status, [AssignmentSubmission::DRAFT, AssignmentSubmission::RETURNED], true);
    }

    /** The unsubmitted revision being worked on (a draft keeps its number; a returned submission works on the next one), if it exists yet. */
    public static function working(AssignmentSubmission $submission): ?SubmissionRevision
    {
        $number = $submission->status === AssignmentSubmission::RETURNED ? $submission->current_revision + 1 : $submission->current_revision;

        return $submission->revisions()->where('number', $number)->whereNull('submitted_at')->first();
    }

    /** Resubmissions already made: every submitted revision after the first. */
    public static function resubmissionsUsed(AssignmentSubmission $submission): int
    {
        return max(0, (int) $submission->revisions()->whereNotNull('submitted_at')->count() - 1);
    }

    public static function revisionsLeft(Assignment $assignment, ?AssignmentSubmission $submission): int
    {
        return $submission === null ? $assignment->max_revisions : max(0, $assignment->max_revisions - self::resubmissionsUsed($submission));
    }
}
