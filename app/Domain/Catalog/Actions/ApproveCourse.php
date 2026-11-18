<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\CourseReadiness;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Events\CourseReviewed;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pending Review to Published. The mutation behind the review queue's approve button: RecordReviewDecision (or ReviewCourse)
 * rechecks the `publish` grant, runs this in one transaction and writes the audit entry.
 */
final class ApproveCourse
{
    public function __invoke(Course $course, User $actor, ?string $note = null): Course
    {
        $locked = Course::query()->lockForUpdate()->findOrFail($course->getKey());
        if ($locked->status !== CourseStatus::PendingReview) {
            throw ValidationException::withMessages(['status' => __('course_wizard.review.not_pending')]);
        }
        CourseReadiness::assertReady($locked, CourseReadiness::PUBLISH);

        $locked->forceFill(['status' => CourseStatus::Published, 'reviewed_at' => now(), 'reviewed_by' => $actor->getKey(), 'review_reason' => $note])->save();
        DB::afterCommit(static fn () => event(new CourseReviewed($locked, true, $note)));

        return $locked;
    }
}
