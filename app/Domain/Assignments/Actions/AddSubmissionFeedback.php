<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Actions;

use App\Domain\Assignments\AssignmentAccess;
use App\Domain\Assignments\ReviewAccess;
use App\Domain\Assignments\ReviewRefusedException;
use App\Domain\Audit\AuditLogger;
use App\Models\AssignmentSubmission;
use App\Models\SubmissionFeedback;
use App\Models\User;
use RuntimeException;

/** A reviewer comment in the feedback thread without a decision. It does not notify (decisions do). */
final class AddSubmissionFeedback
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $reviewer, AssignmentSubmission $submission, string $body): SubmissionFeedback
    {
        ReviewAccess::authorize($reviewer, AssignmentAccess::courseOf($submission->assignment));
        $body = trim($body);
        if ($body === '') {
            throw new ReviewRefusedException(ReviewRefusedException::FEEDBACK_NEEDED);
        }
        if ($submission->status === AssignmentSubmission::DRAFT) {
            throw new ReviewRefusedException(ReviewRefusedException::NOT_DECIDABLE);
        }

        $feedback = SubmissionFeedback::query()->create([
            'submission_id' => $submission->getKey(), 'revision_number' => $submission->current_revision, 'author_id' => $reviewer->getKey(), 'body' => $body,
        ]);
        if ($this->audit->record('assignment.feedback_added', $submission->assignment, (int) $reviewer->getKey(), null, null, ['submission_id' => $submission->getKey(), 'feedback_id' => $feedback->getKey()]) === null) {
            throw new RuntimeException('Unable to record review audit.');
        }

        return $feedback;
    }
}
