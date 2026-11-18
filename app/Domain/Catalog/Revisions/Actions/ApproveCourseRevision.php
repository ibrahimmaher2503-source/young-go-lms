<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Domain\Catalog\Revisions\Events\CourseRevisionReviewed;
use App\Domain\Catalog\Revisions\RevisionDiff;
use App\Models\CourseRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Pending to approved: applies the revision to the live course atomically. The mutation behind the review queue's approve
 * button; RecordReviewDecision (via ApproveRejectAction or ReviewCourseRevision) rechecks the grant and writes the decision audit.
 */
final class ApproveCourseRevision
{
    public function __construct(private readonly ApplyCourseRevision $apply, private readonly AuditLogger $audit) {}

    public function __invoke(CourseRevision $revision, User $actor, ?string $note = null): CourseRevision
    {
        $locked = CourseRevision::query()->lockForUpdate()->findOrFail($revision->getKey());
        if ($locked->status !== CourseRevisionStatus::Pending) {
            throw ValidationException::withMessages(['status' => __('course_revisions.not_pending')]);
        }
        if ((int) $locked->author_id === (int) $actor->getKey()) {
            throw ValidationException::withMessages(['status' => __('course_revisions.self_review')]);
        }

        ($this->apply)($locked);

        $locked->forceFill([
            'status' => CourseRevisionStatus::Approved, 'reviewer_id' => $actor->getKey(), 'reviewed_at' => now(), 'applied_at' => now(),
            'reason' => $note, 'open_course_id' => null,
        ])->save();

        $changes = RevisionDiff::between($locked->base, $locked->payload);
        if ($this->audit->record('course.revision_applied', $locked->course, (int) $actor->getKey(), null, ['revision' => $locked->number, 'changes' => count($changes)], [
            'revision_id' => $locked->getKey(), 'changed' => array_map(static fn (array $row): string => $row['scope'].':'.$row['type'].':'.$row['label'], $changes),
        ]) === null) {
            throw new RuntimeException('Unable to record course revision audit.');
        }
        DB::afterCommit(static fn () => event(new CourseRevisionReviewed($locked, true, $note)));

        return $locked;
    }
}
