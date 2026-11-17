<?php

declare(strict_types=1);

namespace App\Domain\Assignments;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\SubmissionFile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Who may open a submitted file: its owner, a parent linked to the owner, or active staff who can edit the course. Fails closed. */
final class SubmissionFileAccess
{
    public static function allows(User $viewer, SubmissionFile $file): bool
    {
        if ($viewer->getAttribute('status') !== 'active') {
            return false;
        }

        $submission = $file->revision?->submission;
        if (! $submission instanceof AssignmentSubmission) {
            return false;
        }

        if ((int) $submission->learner_id === (int) $viewer->getKey()) {
            return true;
        }

        $owner = $submission->learner;
        if ($owner instanceof User && Gate::forUser($viewer)->allows('guardian.child.access', $owner)) {
            return true;
        }

        return self::staffCanReview($viewer, $submission->assignment);
    }

    /** Active staff with the assessment permission who can edit the course the assignment sits in. */
    public static function staffCanReview(User $viewer, Assignment $assignment): bool
    {
        $item = $assignment->item()->first();
        if ($item === null || $viewer->getAttribute('status') !== 'active' || ! $viewer->can('assessment.manage')) {
            return false;
        }

        return Gate::forUser($viewer)->allows('update', $item->course);
    }
}
