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

/** Finish step "Publish": direct publication by a `publish` holder once every submit and publish check passes (audited). */
final class PublishReadyCourse
{
    public function __construct(private readonly AuditLogger $audit, private readonly PublishCourse $publish) {}

    public function __invoke(User $actor, Course $course): Course
    {
        Gate::forUser($actor)->authorize('publish', $course);

        return DB::transaction(function () use ($actor, $course): Course {
            $current = Course::query()->lockForUpdate()->findOrFail($course->getKey());
            if (! in_array($current->status, [CourseStatus::Draft, CourseStatus::PendingReview, CourseStatus::Private, CourseStatus::Upcoming], true)) {
                throw ValidationException::withMessages(['status' => __('course_wizard.review.cannot_publish')]);
            }
            CourseReadiness::assertReady($current, CourseReadiness::PUBLISH);

            $before = $current->status->value;
            ($this->publish)($current, $actor);
            $current->refresh()->forceFill(['reviewed_at' => now(), 'reviewed_by' => $actor->getKey()])->save();
            if ($this->audit->record('course.published', $current, (int) $actor->getKey(), ['status' => $before], ['status' => 'published']) === null) {
                throw new RuntimeException('Unable to record course publication audit.');
            }

            return $current;
        });
    }
}
