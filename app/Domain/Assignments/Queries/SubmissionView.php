<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Queries;

use App\Domain\Assignments\AssignmentRules;
use App\Domain\Assignments\SubmissionRevisions;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\SubmissionDecision;
use App\Models\SubmissionFeedback;
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
     *     decision: array{decision: string, score: float|null, penalty: int|null, final_score: float|null, at: Carbon}|null, feedback: list<array{body: string, revision: int|null, author: string, at: Carbon}>,
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
            'decision' => self::decision($submission), 'feedback' => self::feedback($submission),
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

    /** @return array{decision: string, score: float|null, penalty: int|null, final_score: float|null, at: Carbon}|null the latest reviewer decision, if any */
    private static function decision(?AssignmentSubmission $submission): ?array
    {
        $latest = $submission?->decisions()->latest('id')->first();
        if (! $latest instanceof SubmissionDecision) {
            return null;
        }

        return [
            'decision' => $latest->decision, 'score' => $latest->score === null ? null : (float) $latest->score, 'penalty' => $latest->late_penalty_percent,
            'final_score' => $latest->final_score === null ? null : (float) $latest->final_score, 'at' => $latest->created_at,
        ];
    }

    /** @return list<array{body: string, revision: int|null, author: string, at: Carbon}> the reviewer feedback thread, oldest first */
    private static function feedback(?AssignmentSubmission $submission): array
    {
        if ($submission === null) {
            return [];
        }

        return $submission->feedback()->with('author')->get()->map(static fn (SubmissionFeedback $f): array => [
            'body' => $f->body, 'revision' => $f->revision_number, 'author' => (string) ($f->author?->getAttribute('display_name') ?: $f->author?->getAttribute('name') ?: ''), 'at' => $f->created_at,
        ])->all();
    }
}
