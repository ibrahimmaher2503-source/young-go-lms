<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Domain\Catalog\Revisions\RevisionSnapshot;
use App\Models\Course;
use App\Models\CourseRevision;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Opens the draft copy of a published course's editable content. One open revision per course. Passing a rejected
 * revision of the same course starts the new draft from its proposed content so the author can fix it and resubmit.
 */
final class OpenCourseRevision
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Course $course, ?CourseRevision $continueFrom = null): CourseRevision
    {
        Gate::forUser($actor)->authorize('update', $course);

        try {
            return DB::transaction(function () use ($actor, $course, $continueFrom): CourseRevision {
                $locked = Course::query()->lockForUpdate()->findOrFail($course->getKey());
                if ($locked->status !== CourseStatus::Published) {
                    throw ValidationException::withMessages(['course' => __('course_revisions.not_published')]);
                }
                if (CourseRevision::query()->where('course_id', $locked->getKey())->whereIn('status', [CourseRevisionStatus::Draft->value, CourseRevisionStatus::Pending->value])->exists()) {
                    throw ValidationException::withMessages(['course' => __('course_revisions.already_open')]);
                }

                $base = RevisionSnapshot::capture($locked);
                $payload = $base;
                if ($continueFrom !== null && $continueFrom->course_id === $locked->getKey() && $continueFrom->status === CourseRevisionStatus::Rejected) {
                    $payload = $continueFrom->payload;
                }

                $revision = CourseRevision::query()->create([
                    'course_id' => $locked->getKey(), 'author_id' => $actor->getKey(),
                    'number' => (int) CourseRevision::query()->where('course_id', $locked->getKey())->max('number') + 1,
                    'status' => CourseRevisionStatus::Draft, 'base' => $base, 'payload' => $payload, 'open_course_id' => $locked->getKey(),
                ]);

                if ($this->audit->record('course.revision_opened', $locked, (int) $actor->getKey(), null, ['revision' => $revision->number], ['revision_id' => $revision->getKey()]) === null) {
                    throw new RuntimeException('Unable to record course revision audit.');
                }

                return $revision;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['course' => __('course_revisions.already_open')]);
        }
    }
}
