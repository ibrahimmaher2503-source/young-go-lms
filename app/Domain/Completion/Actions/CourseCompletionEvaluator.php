<?php

declare(strict_types=1);

namespace App\Domain\Completion\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Completion\CompletionReport;
use App\Domain\Completion\CourseCompletion;
use App\Domain\Completion\Events\CourseCompleted;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Checks one learner against the course rules and records completion once. Safe to call any number of times: an existing
 * completion is returned untouched (completion is never undone by a later change), and the unique key stops a double row
 * when two events race. Courses with manual approval only measure; a person records the completion (ApproveCourseCompletion).
 * CourseCompleted is dispatched only by the call that created the row.
 */
final class CourseCompletionEvaluator
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return CourseCompletion|null the completion, or null when the learner has not completed (yet) */
    public function __invoke(User $learner, Course $course): ?CourseCompletion
    {
        $existing = CourseCompletion::query()->where('course_id', $course->getKey())->where('learner_id', $learner->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        $report = CompletionReport::measure($learner, $course);
        if (! $report->eligible() || $report->rules->manual_approval) {
            return null;
        }

        try {
            $created = DB::transaction(function () use ($learner, $course, $report): CourseCompletion {
                $row = CourseCompletion::query()->create([
                    'course_id' => $course->getKey(), 'learner_id' => $learner->getKey(), 'completed_at' => now(),
                    'source' => CourseCompletion::AUTO, 'snapshot' => ['met' => $report->met(), 'rules' => $report->rules->values()],
                ]);
                if ($this->audit->record('course.completed', $course, null, null, ['learner_id' => $learner->getKey(), 'source' => CourseCompletion::AUTO]) === null) {
                    throw new RuntimeException('Unable to record completion audit.');
                }

                return $row;
            });
        } catch (UniqueConstraintViolationException) {
            return CourseCompletion::query()->where('course_id', $course->getKey())->where('learner_id', $learner->getKey())->first();
        }

        event(new CourseCompleted($created));

        return $created;
    }
}
