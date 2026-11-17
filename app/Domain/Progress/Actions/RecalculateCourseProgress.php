<?php

declare(strict_types=1);

namespace App\Domain\Progress\Actions;

use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\LessonProgress;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;

/**
 * Rebuilds one learner's course progress from the lessons that count today: published, not archived, marked required.
 * Quizzes and assignments have no completion yet (P6), so they are left out rather than blocking every course.
 * Called after every progress event and whenever the progress is read, so an archived or new lesson is reflected at once.
 */
final class RecalculateCourseProgress
{
    public function __invoke(User $learner, Course $course): CourseProgress
    {
        $required = $this->requiredItemIds($course);
        $done = $required === [] ? 0 : LessonProgress::query()->where('learner_id', $learner->getKey())->whereIn('curriculum_item_id', $required)->where('status', LessonProgress::COMPLETED)->count();
        $total = count($required);

        $last = LessonProgress::query()->where('learner_id', $learner->getKey())
            ->whereIn('curriculum_item_id', CurriculumItem::query()->where('course_id', $course->getKey())->pluck('id'))
            ->orderByDesc('last_accessed_at')->first();

        $progress = CourseProgress::query()->firstOrNew(['learner_id' => $learner->getKey(), 'course_id' => $course->getKey()]);
        $complete = $total > 0 && $done >= $total;
        $progress->forceFill([
            'percent' => $total === 0 ? 0 : (int) floor($done / $total * 100),
            'completed_required' => $done, 'total_required' => $total,
            'last_item_id' => $last?->curriculum_item_id, 'last_position_seconds' => $last->position_seconds ?? 0,
            'last_activity_at' => $last?->last_accessed_at,
            'completed_at' => $complete ? ($progress->completed_at ?? now()) : null,
        ])->save();

        return $progress;
    }

    /** @return list<int> */
    private function requiredItemIds(Course $course): array
    {
        return CurriculumOutline::forStudents($course)->flatMap(fn ($section) => $section->items)
            ->filter(fn (CurriculumItem $item): bool => $item->required && $item->itemable instanceof Lesson)
            ->map(fn (CurriculumItem $item): int => (int) $item->getKey())->values()->all();
    }
}
