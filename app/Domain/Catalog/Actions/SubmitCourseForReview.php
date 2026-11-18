<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CourseReadiness;
use App\Domain\Catalog\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Draft to Pending Review (PRD 9.4, D-007). Needs the `update` grant and a course that passes the submit checks. */
final class SubmitCourseForReview
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Course $course): Course
    {
        Gate::forUser($actor)->authorize('update', $course);

        return DB::transaction(function () use ($actor, $course): Course {
            $locked = Course::query()->lockForUpdate()->findOrFail($course->getKey());
            if ($locked->status !== CourseStatus::Draft) {
                throw ValidationException::withMessages(['status' => __('course_wizard.review.not_draft')]);
            }
            CourseReadiness::assertReady($locked, CourseReadiness::SUBMIT);

            $locked->forceFill(['status' => CourseStatus::PendingReview, 'submitted_at' => now(), 'review_reason' => null])->save();
            if ($this->audit->record('course.submitted_for_review', $locked, (int) $actor->getKey(), ['status' => 'draft'], ['status' => 'pending_review']) === null) {
                throw new RuntimeException('Unable to record course submission audit.');
            }

            return $locked;
        });
    }
}
