<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Models\CourseRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Withdraws an open (draft or pending) revision. The live course never saw it; the record stays in the history. */
final class DiscardCourseRevision
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, CourseRevision $revision): CourseRevision
    {
        Gate::forUser($actor)->authorize('update', $revision);

        return DB::transaction(function () use ($actor, $revision): CourseRevision {
            $locked = CourseRevision::query()->lockForUpdate()->findOrFail($revision->getKey());
            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages(['revision' => __('course_revisions.not_open')]);
            }

            $locked->forceFill(['status' => CourseRevisionStatus::Discarded, 'open_course_id' => null])->save();
            if ($this->audit->record('course.revision_discarded', $locked->course, (int) $actor->getKey(), null, null, ['revision_id' => $locked->getKey(), 'revision' => $locked->number]) === null) {
                throw new RuntimeException('Unable to record course revision audit.');
            }

            return $locked;
        });
    }
}
