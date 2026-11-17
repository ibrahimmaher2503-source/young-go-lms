<?php

declare(strict_types=1);

namespace App\Domain\Completion\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Completion\CompletionReport;
use App\Domain\Completion\CourseCompletion;
use App\Domain\Completion\Events\CourseCompleted;
use App\Domain\Entitlements\CourseLearners;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Staff mark a learner as having completed the course by hand: the way out of "manual approval" courses and the override
 * for anything the rules do not see. A reason is required and the audit trail keeps who, why and what the rules said.
 * Needs the course update gate (an instructor with manage scope, or an authorized admin).
 */
final class ApproveCourseCompletion
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Course $course, User $learner, string $reason): CourseCompletion
    {
        Gate::forUser($actor)->authorize('update', $course);

        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('completion.reason_required')]);
        }
        if (! CourseLearners::query($course)->whereKey($learner->getKey())->exists()) {
            throw ValidationException::withMessages(['learner' => __('completion.not_a_learner')]);
        }
        if (CourseCompletion::query()->where('course_id', $course->getKey())->where('learner_id', $learner->getKey())->exists()) {
            throw ValidationException::withMessages(['learner' => __('completion.already_completed')]);
        }

        $report = CompletionReport::measure($learner, $course);

        try {
            $row = DB::transaction(function () use ($actor, $course, $learner, $reason, $report): CourseCompletion {
                $row = CourseCompletion::query()->create([
                    'course_id' => $course->getKey(), 'learner_id' => $learner->getKey(), 'completed_at' => now(), 'source' => CourseCompletion::MANUAL,
                    'approved_by' => $actor->getKey(), 'reason' => $reason, 'snapshot' => ['met' => $report->met(), 'rules' => $report->rules->values()],
                ]);
                $after = ['learner_id' => $learner->getKey(), 'source' => CourseCompletion::MANUAL, 'reason' => $reason, 'rules_met' => $report->eligible()];
                if ($this->audit->record('course.completion_approved', $course, (int) $actor->getKey(), null, $after) === null) {
                    throw new RuntimeException('Unable to record completion audit.');
                }

                return $row;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['learner' => __('completion.already_completed')]);
        }

        event(new CourseCompleted($row));

        return $row;
    }
}
