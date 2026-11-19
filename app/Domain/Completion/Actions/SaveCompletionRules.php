<?php

declare(strict_types=1);

namespace App\Domain\Completion\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Completion\CourseCompletionRule;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/** Saves the completion rules of a course (O-020). Needs the course update gate. Earlier completions are never revoked by a rule change. */
final class SaveCompletionRules
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $input  lesson_percent, require_quizzes, require_assignments, attendance_percent, manual_approval */
    public function __invoke(User $actor, Course $course, array $input): CourseCompletionRule
    {
        Gate::forUser($actor)->authorize('update', $course);

        $input['lesson_percent'] = ($input['lesson_percent'] ?? null) === '' ? null : ($input['lesson_percent'] ?? null);
        $input['attendance_percent'] = ($input['attendance_percent'] ?? null) === '' ? null : ($input['attendance_percent'] ?? null);
        $withPassMark = array_key_exists('quiz_pass_mark_percent', $input);
        $input['quiz_pass_mark_percent'] = ($input['quiz_pass_mark_percent'] ?? null) === '' ? null : ($input['quiz_pass_mark_percent'] ?? null);
        $data = Validator::make($input, [
            'quiz_pass_mark_percent' => ['nullable', 'integer', 'between:1,100'],
            'lesson_percent' => ['nullable', 'integer', 'between:1,100'],
            'attendance_percent' => ['nullable', 'integer', 'between:1,100'],
            'require_quizzes' => ['boolean'],
            'require_assignments' => ['boolean'],
            'manual_approval' => ['boolean'],
        ], [], [
            'quiz_pass_mark_percent' => __('completion.quiz_pass_mark'), 'lesson_percent' => __('completion.lesson_percent'), 'attendance_percent' => __('completion.attendance_percent'),
        ])->validate();

        return DB::transaction(function () use ($actor, $course, $data, $withPassMark): CourseCompletionRule {
            if ($withPassMark) {
                $this->savePassMark($actor, $course, $data['quiz_pass_mark_percent'] === null ? null : (int) $data['quiz_pass_mark_percent']);
            }

            $rule = CourseCompletionRule::forCourse($course);
            $before = $rule->values();
            $rule->forceFill([
                'lesson_percent' => $data['lesson_percent'] ?? null, 'attendance_percent' => $data['attendance_percent'] ?? null,
                'require_quizzes' => (bool) ($data['require_quizzes'] ?? false), 'require_assignments' => (bool) ($data['require_assignments'] ?? false),
                'manual_approval' => (bool) ($data['manual_approval'] ?? false),
            ])->save();

            if ($this->audit->record('course.completion_rules_saved', $course, (int) $actor->getKey(), $before, $rule->values()) === null) {
                throw new RuntimeException('Unable to record completion rules audit.');
            }

            return $rule;
        });
    }

    /** O-020: the course default pass mark for its quizzes. Audited on its own so the completion-rules audit payload is unchanged. */
    private function savePassMark(User $actor, Course $course, ?int $mark): void
    {
        $before = $course->quiz_pass_mark_percent;
        if ($before === $mark) {
            return;
        }
        $course->forceFill(['quiz_pass_mark_percent' => $mark])->save();
        if ($this->audit->record('course.quiz_pass_mark_saved', $course, (int) $actor->getKey(), ['quiz_pass_mark_percent' => $before], ['quiz_pass_mark_percent' => $mark]) === null) {
            throw new RuntimeException('Unable to record quiz pass mark audit.');
        }
    }
}
