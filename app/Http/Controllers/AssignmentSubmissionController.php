<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Assignments\Actions\SaveSubmissionDraft;
use App\Domain\Assignments\Actions\SubmitAssignment;
use App\Domain\Assignments\AssignmentRefusedException;
use App\Domain\Assignments\SubmissionFileAccess;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\SubmissionFile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The learner side of an assignment. A learner without access, or someone else's submission or file, gets a 404. The
 * rules (types, sizes, late policy, revisions) live in the Actions; stored paths are never sent to the browser.
 */
final class AssignmentSubmissionController
{
    private const array DOWNLOAD_HEADERS = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

    public function __construct(
        private readonly ActingLearner $acting,
        private readonly EntitlementService $access,
        private readonly SaveSubmissionDraft $save,
        private readonly SubmitAssignment $submit,
    ) {}

    public function save(Request $request, Course $course, CurriculumItem $item): RedirectResponse
    {
        $learner = $this->learner();
        $this->guardItem($course, $item);

        try {
            $this->saveDraft($request, $learner, $item);
        } catch (AssignmentRefusedException $exception) {
            return $this->refused($exception, $course, $item);
        }

        return $this->back($course, $item)->with('assignment_notice', __('assignment.saved'));
    }

    /** Saves what is on the form first, so one click on "Submit" never loses the latest text or files. */
    public function submit(Request $request, Course $course, CurriculumItem $item): RedirectResponse
    {
        $learner = $this->learner();
        $this->guardItem($course, $item);

        try {
            $this->saveDraft($request, $learner, $item);
            $done = $this->submit->__invoke($learner, $item);
        } catch (AssignmentRefusedException $exception) {
            return $this->refused($exception, $course, $item);
        }

        return $this->back($course, $item)->with('assignment_notice', __('assignment.status_notice.'.$done->status));
    }

    /** Streams one submitted file to its owner, a linked parent or staff of the course; everyone else gets a 404. */
    public function download(Request $request, SubmissionFile $file): StreamedResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User && SubmissionFileAccess::allows($viewer, $file), 404);

        return $this->stream($file->path, $file->display_name);
    }

    /** The instructor's brief files, for learners who can open the assignment and for staff of the course. */
    public function attachment(Request $request, AssignmentAttachment $attachment): StreamedResponse
    {
        $viewer = $request->user();
        $assignment = $attachment->assignment;
        $item = $assignment->item()->first();
        abort_unless($viewer instanceof User && $item instanceof CurriculumItem && $assignment->archived_at === null, 404);

        $learner = $this->acting->current();
        $allowed = SubmissionFileAccess::staffCanReview($viewer, $assignment)
            || ($learner instanceof User && $this->access->canAccessItem($learner, $item)->allowed);
        abort_unless($allowed, 404);

        $title = trim((string) $attachment->title);
        $extension = pathinfo($attachment->path, PATHINFO_EXTENSION);

        return $this->stream($attachment->path, ($title !== '' ? $title : 'file').($extension !== '' && ! str_ends_with($title, '.'.$extension) ? '.'.$extension : ''));
    }

    private function saveDraft(Request $request, User $learner, CurriculumItem $item): void
    {
        $text = $request->has('text') ? (string) $request->input('text') : null;
        $files = array_values(array_filter((array) $request->file('files', []), static fn ($file): bool => $file !== null));
        $remove = array_map('intval', (array) $request->input('remove', []));

        $this->save->__invoke($learner, $item, $text, $files, $remove);
    }

    private function stream(?string $path, string $name): StreamedResponse
    {
        $disk = Storage::disk(Config::string('media.disk'));
        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->download($path, $name, self::DOWNLOAD_HEADERS);
    }

    private function refused(AssignmentRefusedException $exception, Course $course, CurriculumItem $item): RedirectResponse
    {
        abort_if($exception->reason === AssignmentRefusedException::NO_ACCESS, 404);

        return $this->back($course, $item)->with('assignment_error', $exception->getMessage())->withInput($exception->reason === AssignmentRefusedException::INVALID_FILE ? [] : ['text' => request()->input('text')]);
    }

    private function back(Course $course, CurriculumItem $item): RedirectResponse
    {
        return redirect()->route('learn.show', ['course' => $course->getKey(), 'item' => $item->getKey()]);
    }

    private function guardItem(Course $course, CurriculumItem $item): void
    {
        abort_unless((int) $item->course_id === (int) $course->getKey() && $item->itemable instanceof Assignment, 404);
    }

    private function learner(): User
    {
        $learner = $this->acting->current();
        abort_unless($learner instanceof User, 401);

        return $learner;
    }
}
