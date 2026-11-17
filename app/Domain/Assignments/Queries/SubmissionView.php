<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Queries;

use App\Domain\Assignments\AssignmentRules;
use App\Domain\Assignments\SubmissionRevisions;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\SubmissionRevision;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/** What a learner sees on an assignment page. Plain arrays: no storage paths, only ids the download route resolves. */
final class SubmissionView
{
    /**
     * @return array{
     *     assignment: Assignment, submission: AssignmentSubmission|null, status: string, revisions: list<array<string, mixed>>, working: array<string, mixed>|null,
     *     can_edit: bool, can_submit: bool, revisions_left: int, is_past_due: bool, late_blocked: bool, due_at: Carbon|null,
     *     rules: array{text: bool, kinds: list<string>, accept: string, max_files: int, max_text: int, limit_kb: int}
     * }
     */
    public static function for(User $learner, Assignment $assignment): array
    {
        $submission = AssignmentSubmission::query()->where('assignment_id', $assignment->getKey())->where('learner_id', $learner->getKey())->first();
        $revisions = $submission === null ? collect() : $submission->revisions()->with('files')->get();
        $workingModel = $submission !== null && SubmissionRevisions::isEditableStatus($submission->status) ? SubmissionRevisions::working($submission) : null;

        $pastDue = $assignment->isPastDue();
        $lateBlocked = $pastDue && $assignment->late_policy === Assignment::LATE_NOT_ALLOWED;
        $left = SubmissionRevisions::revisionsLeft($assignment, $submission);
        $status = $submission === null ? 'not_started' : $submission->status;
        $editable = $submission === null || (SubmissionRevisions::isEditableStatus($status) && ($status !== AssignmentSubmission::RETURNED || $left > 0));

        return [
            'assignment' => $assignment, 'submission' => $submission, 'status' => $status,
            'revisions' => $revisions->filter(fn (SubmissionRevision $r): bool => ! $r->isDraft())->map(fn (SubmissionRevision $r): array => self::revision($r))->values()->all(),
            'working' => $workingModel === null ? null : self::revision($workingModel->load('files')),
            'can_edit' => $editable, 'can_submit' => $editable && ! $lateBlocked,
            'revisions_left' => $left, 'is_past_due' => $pastDue, 'late_blocked' => $lateBlocked, 'due_at' => $assignment->due_at,
            'rules' => [
                'text' => AssignmentRules::allowsText($assignment), 'kinds' => AssignmentRules::fileKinds($assignment),
                'accept' => implode(',', AssignmentRules::acceptedMimes($assignment)), 'max_files' => Config::integer('assignments.max_files'),
                'max_text' => Config::integer('assignments.max_text_length'), 'limit_kb' => AssignmentRules::limitKilobytes($assignment),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function revision(SubmissionRevision $revision): array
    {
        return [
            'number' => $revision->number, 'text' => $revision->text_body, 'submitted_at' => $revision->submitted_at, 'is_late' => $revision->is_late,
            'late_penalty_percent' => $revision->late_penalty_percent,
            'files' => $revision->files->map(fn ($file): array => ['id' => $file->id, 'name' => $file->display_name, 'kind' => $file->kind, 'mime' => $file->mime, 'size' => $file->size])->all(),
        ];
    }
}
