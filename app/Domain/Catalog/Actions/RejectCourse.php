<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Events\CourseReviewed;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pending Review back to Draft with the reviewer's reason kept on the course (the instructor sees and is notified of it). */
final class RejectCourse
{
    public function __invoke(Course $course, User $actor, ?string $reason): Course
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('validation.required', ['attribute' => __('common.review_reason')])]);
        }
        $locked = Course::query()->lockForUpdate()->findOrFail($course->getKey());
        if ($locked->status !== CourseStatus::PendingReview) {
            throw ValidationException::withMessages(['status' => __('course_wizard.review.not_pending')]);
        }

        $locked->forceFill(['status' => CourseStatus::Draft, 'reviewed_at' => now(), 'reviewed_by' => $actor->getKey(), 'review_reason' => $reason])->save();
        DB::afterCommit(static fn () => event(new CourseReviewed($locked, false, $reason)));

        return $locked;
    }
}
