<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Actions;

use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Domain\Catalog\Revisions\Events\CourseRevisionReviewed;
use App\Models\CourseRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pending to rejected, with the reviewer's reason. The live course is untouched; the author can reopen from the rejected content. */
final class RejectCourseRevision
{
    public function __invoke(CourseRevision $revision, User $actor, ?string $reason): CourseRevision
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('validation.required', ['attribute' => __('common.review_reason')])]);
        }
        $locked = CourseRevision::query()->lockForUpdate()->findOrFail($revision->getKey());
        if ($locked->status !== CourseRevisionStatus::Pending) {
            throw ValidationException::withMessages(['status' => __('course_revisions.not_pending')]);
        }
        if ((int) $locked->author_id === (int) $actor->getKey()) {
            throw ValidationException::withMessages(['status' => __('course_revisions.self_review')]);
        }

        $locked->forceFill(['status' => CourseRevisionStatus::Rejected, 'reviewer_id' => $actor->getKey(), 'reviewed_at' => now(), 'reason' => $reason, 'open_course_id' => null])->save();
        DB::afterCommit(static fn () => event(new CourseRevisionReviewed($locked, false, $reason)));

        return $locked;
    }
}
