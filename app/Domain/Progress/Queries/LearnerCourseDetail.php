<?php

declare(strict_types=1);

namespace App\Domain\Progress\Queries;

use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Entitlements\CourseLearners;
use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\LessonProgress;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** One learner's lesson-by-lesson state inside one course, for the instructors who run it. Reads only; nothing is written. */
final class LearnerCourseDetail
{
    /** @return Builder<User> */
    public static function courseLearners(Course $course): Builder
    {
        $activity = LessonProgress::query()->select('learner_id')->selectRaw('max(last_accessed_at) as last_activity_at')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed_items', [LessonProgress::COMPLETED])
            ->whereIn('curriculum_item_id', CurriculumOutline::studentOrder($course))
            ->groupBy('learner_id');
        $progress = CourseProgress::query()->where('course_id', $course->getKey())
            ->select('learner_id', 'percent');

        return CourseLearners::query($course)
            ->leftJoinSub($progress, 'learner_progress', 'learner_progress.learner_id', '=', 'users.id')
            ->leftJoinSub($activity, 'learner_activity', 'learner_activity.learner_id', '=', 'users.id')
            ->addSelect('learner_progress.percent', 'learner_activity.last_activity_at', 'learner_activity.completed_items');
    }

    /**
     * @param  list<int>  $learnerIds
     * @return array<int, array{quizzes?: list<string>, assignments?: list<string>}>
     */
    public static function assessmentStates(Course $course, array $learnerIds): array
    {
        $items = CurriculumOutline::forStudents($course)->flatMap(fn ($section) => $section->items);
        $quizzes = $items->filter(fn (CurriculumItem $item): bool => $item->itemable instanceof Quiz)->keyBy('itemable_id');
        $assignments = $items->filter(fn (CurriculumItem $item): bool => $item->itemable instanceof Assignment)->keyBy('itemable_id');
        $latestAttempts = QuizAttempt::query()->selectRaw('max(id)')
            ->whereIn('quiz_id', $quizzes->keys())->whereIn('learner_id', $learnerIds)
            ->groupBy('quiz_id', 'learner_id');
        $result = [];

        foreach (QuizAttempt::query()->whereIn('id', $latestAttempts)->get(['quiz_id', 'learner_id', 'status', 'needs_review', 'passed']) as $attempt) {
            $status = match (true) {
                $attempt->isOpen() => __('courses.lesson_status.in_progress'),
                $attempt->needs_review => __('quiz.attempt.awaiting_review'),
                $attempt->passed === true => __('quiz.attempt.passed'),
                $attempt->passed === false => __('quiz.attempt.failed'),
                default => __('quiz.attempt.submitted_label'),
            };
            $result[$attempt->learner_id]['quizzes'][] = (string) $quizzes->get($attempt->quiz_id)?->itemable?->getAttribute('title').' · '.$status;
        }

        foreach (AssignmentSubmission::query()->whereIn('assignment_id', $assignments->keys())->whereIn('learner_id', $learnerIds)->get(['assignment_id', 'learner_id', 'status']) as $submission) {
            $result[$submission->learner_id]['assignments'][] = (string) $assignments->get($submission->assignment_id)?->itemable?->getAttribute('title').' · '.__('assignment.statuses.'.$submission->status);
        }

        return $result;
    }

    /** @return list<array{title: string, type: string, status: string, percent: int, last_at: string|null}> */
    public static function for(User $learner, Course $course): array
    {
        $items = CurriculumOutline::forStudents($course)->flatMap(fn ($section) => $section->items)
            ->filter(fn (CurriculumItem $item): bool => $item->itemable instanceof Lesson)->values();
        $progress = LessonProgress::query()->where('learner_id', $learner->getKey())->whereIn('curriculum_item_id', $items->pluck('id'))->get()->keyBy('curriculum_item_id');

        return $items->map(function (CurriculumItem $item) use ($progress): array {
            $row = $progress->get($item->getKey());
            $lesson = $item->itemable;

            return [
                'title' => (string) $lesson?->getAttribute('title'),
                'type' => (string) $lesson?->getAttribute('type'),
                'status' => $row === null ? 'not_started' : $row->status,
                'percent' => (int) ($row->percent ?? 0),
                'last_at' => $row?->last_accessed_at?->isoFormat('LLL'),
            ];
        })->all();
    }
}
