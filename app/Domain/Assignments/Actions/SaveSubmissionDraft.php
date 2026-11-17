<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Actions;

use App\Domain\Assignments\AssignmentFiles;
use App\Domain\Assignments\AssignmentRefusedException;
use App\Domain\Assignments\AssignmentRules;
use App\Domain\Assignments\SubmissionRevisions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\EntitlementService;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CurriculumItem;
use App\Models\SubmissionFile;
use App\Models\SubmissionRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/**
 * Saves the learner's work in progress: written answer and/or files. The submission and its first revision are created
 * on the first save. A returned submission gets its next revision here, so the handed-in revision is never overwritten.
 * Files are validated by content (AssignmentFiles) and kept private. Never submits.
 */
final class SaveSubmissionDraft
{
    public function __construct(private readonly EntitlementService $access, private readonly AuditLogger $audit) {}

    /**
     * @param  string|null  $text  null leaves the written answer as it is; a string replaces it
     * @param  list<File>  $files  new uploads, added to the draft
     * @param  list<int>  $removeFileIds  draft files to drop
     * @param  bool  $replaceFiles  drop every existing draft file before adding the new ones
     */
    public function __invoke(User $learner, CurriculumItem $item, ?string $text = null, array $files = [], array $removeFileIds = [], bool $replaceFiles = false): AssignmentSubmission
    {
        $assignment = $item->itemable;
        if (! $assignment instanceof Assignment || $assignment->archived_at !== null || ! $this->access->canAccessItem($learner, $item)->allowed) {
            throw new AssignmentRefusedException(AssignmentRefusedException::NO_ACCESS);
        }

        $text = $text === null ? null : trim($text);
        if ($text !== null && $text !== '') {
            if (! AssignmentRules::allowsText($assignment)) {
                throw new AssignmentRefusedException(AssignmentRefusedException::TYPE_NOT_ALLOWED);
            }
            if (mb_strlen($text) > Config::integer('assignments.max_text_length')) {
                throw new AssignmentRefusedException(AssignmentRefusedException::TEXT_TOO_LONG, __('assignment.refused.text_too_long', ['max' => Config::integer('assignments.max_text_length')]));
            }
        }
        if ($files !== [] && AssignmentRules::fileKinds($assignment) === []) {
            throw new AssignmentRefusedException(AssignmentRefusedException::TYPE_NOT_ALLOWED);
        }
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && ! $file->isValid()) {
                throw new AssignmentRefusedException(AssignmentRefusedException::INVALID_FILE);
            }
        }

        $stored = [];
        $obsolete = [];

        try {
            $submission = DB::transaction(function () use ($learner, $assignment, $text, $files, $removeFileIds, $replaceFiles, &$stored, &$obsolete): AssignmentSubmission {
                $submission = AssignmentSubmission::query()->firstOrCreate(
                    ['assignment_id' => $assignment->getKey(), 'learner_id' => $learner->getKey()],
                    ['status' => AssignmentSubmission::DRAFT, 'current_revision' => 1],
                );
                $created = $submission->wasRecentlyCreated;
                $submission = AssignmentSubmission::query()->whereKey($submission->getKey())->lockForUpdate()->firstOrFail();

                if (! SubmissionRevisions::isEditableStatus($submission->status)) {
                    throw new AssignmentRefusedException(AssignmentRefusedException::NOT_EDITABLE);
                }
                if ($submission->status === AssignmentSubmission::RETURNED && SubmissionRevisions::revisionsLeft($assignment, $submission) <= 0) {
                    throw new AssignmentRefusedException(AssignmentRefusedException::REVISIONS_EXHAUSTED);
                }

                $revision = SubmissionRevisions::working($submission) ?? $this->openRevision($submission);

                $existing = $revision->files()->get();
                $removeIds = array_map('intval', $removeFileIds);
                $drop = $replaceFiles ? $existing : $existing->filter(fn (SubmissionFile $file): bool => in_array((int) $file->getKey(), $removeIds, true));
                if ($existing->count() - $drop->count() + count($files) > Config::integer('assignments.max_files')) {
                    throw new AssignmentRefusedException(AssignmentRefusedException::TOO_MANY_FILES, __('assignment.refused.too_many_files', ['max' => Config::integer('assignments.max_files')]));
                }

                foreach ($files as $file) {
                    $info = AssignmentFiles::store($file, AssignmentRules::fileKinds($assignment), AssignmentRules::limitKilobytes($assignment), 'submissions/'.$submission->getKey());
                    $stored[] = $info['path'];
                    $revision->files()->create([
                        'kind' => $info['kind'], 'display_name' => AssignmentFiles::displayName($file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename()),
                        'path' => $info['path'], 'mime' => $info['mime'], 'size' => $info['size'], 'sha256' => $info['sha256'],
                    ]);
                }
                foreach ($drop as $file) {
                    $obsolete[] = $file->path;
                    $file->delete();
                }
                if ($text !== null) {
                    $revision->forceFill(['text_body' => $text === '' ? null : $text])->save();
                }
                $submission->touch();

                $event = $created ? 'assignment.submission_started' : 'assignment.draft_saved';
                if ($this->audit->record($event, $assignment, (int) $learner->getKey(), null, ['submission_id' => $submission->getKey(), 'revision' => $revision->number, 'files_added' => count($files), 'files_removed' => $drop->count()]) === null) {
                    throw new RuntimeException('Unable to record assignment audit.');
                }

                return $submission;
            });
        } catch (Throwable $exception) {
            $this->discard($stored);
            throw $exception;
        }

        $this->discard($obsolete);

        return $submission;
    }

    /** A returned submission starts its next revision from the previous written answer; files are re-uploaded on purpose. */
    private function openRevision(AssignmentSubmission $submission): SubmissionRevision
    {
        $returned = $submission->status === AssignmentSubmission::RETURNED;

        return $submission->revisions()->create([
            'number' => $returned ? $submission->current_revision + 1 : $submission->current_revision,
            'text_body' => $returned ? $submission->currentRevision()?->text_body : null,
        ]);
    }

    /** @param list<string> $paths */
    private function discard(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(Config::string('media.disk'))->delete($paths);
        }
    }
}
