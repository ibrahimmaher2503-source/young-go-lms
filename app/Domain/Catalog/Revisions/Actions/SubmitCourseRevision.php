<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Domain\Catalog\Revisions\Events\CourseRevisionSubmitted;
use App\Domain\Catalog\Revisions\RevisionDiff;
use App\Models\CourseRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Draft to pending: the revision is locked for editing and appears in the reviewers' queue. */
final class SubmitCourseRevision
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, CourseRevision $revision): CourseRevision
    {
        Gate::forUser($actor)->authorize('update', $revision);

        return DB::transaction(function () use ($actor, $revision): CourseRevision {
            $locked = CourseRevision::query()->lockForUpdate()->findOrFail($revision->getKey());
            if ($locked->status !== CourseRevisionStatus::Draft) {
                throw ValidationException::withMessages(['revision' => __('course_revisions.not_draft')]);
            }
            $changes = RevisionDiff::between($locked->base, $locked->payload);
            if ($changes === []) {
                throw ValidationException::withMessages(['revision' => __('course_revisions.no_changes')]);
            }

            $locked->forceFill(['status' => CourseRevisionStatus::Pending, 'submitted_at' => now(), 'reason' => null])->save();
            if ($this->audit->record('course.revision_submitted', $locked->course, (int) $actor->getKey(), ['status' => 'draft'], ['status' => 'pending'], ['revision_id' => $locked->getKey(), 'revision' => $locked->number, 'changes' => count($changes)]) === null) {
                throw new RuntimeException('Unable to record course revision audit.');
            }
            DB::afterCommit(static fn () => event(new CourseRevisionSubmitted($locked)));

            return $locked;
        });
    }
}
