<?php

declare(strict_types=1);

namespace App\Domain\Progress\Queries;

use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\LessonProgress;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Collection;

/** Current progress derived from the published curriculum and saved lesson events. */
final class CourseProgressSnapshot
{
    /**
     * @param  list<int>  $courseIds
     * @return Collection<int, array{done: int, total: int}>
     */
    public static function counts(User $learner, array $courseIds): Collection
    {
        return CurriculumItem::query()
            ->join('course_sections', 'course_sections.id', '=', 'curriculum_items.section_id')
            ->join('lessons', 'lessons.id', '=', 'curriculum_items.itemable_id')
            ->leftJoin('lesson_progress', fn ($join) => $join->on('lesson_progress.curriculum_item_id', '=', 'curriculum_items.id')
                ->where('lesson_progress.learner_id', $learner->id)->where('lesson_progress.status', LessonProgress::COMPLETED))
            ->whereIn('curriculum_items.course_id', $courseIds)
            ->where('curriculum_items.itemable_type', (new Lesson)->getMorphClass())
            ->where('curriculum_items.status', CurriculumItem::STATUS_PUBLISHED)->where('curriculum_items.required', true)
            ->where('course_sections.published', true)->whereNull('course_sections.archived_at')->whereNull('lessons.archived_at')
            ->groupBy('curriculum_items.course_id')
            ->selectRaw('curriculum_items.course_id, count(*) as total, count(lesson_progress.id) as done')
            ->toBase()->get()->mapWithKeys(fn ($row): array => [(int) $row->course_id => ['done' => (int) $row->done, 'total' => (int) $row->total]]);
    }

    /**
     * @param  iterable<int>  $courseIds
     * @return Collection<int, Collection<int, LessonProgress>>
     */
    public static function lessonRows(User $learner, iterable $courseIds): Collection
    {
        $rows = LessonProgress::query()->select('lesson_progress.*', 'curriculum_items.course_id')
            ->join('curriculum_items', 'curriculum_items.id', '=', 'lesson_progress.curriculum_item_id')
            ->where('lesson_progress.learner_id', $learner->getKey())->whereIn('curriculum_items.course_id', $courseIds)
            ->orderByRaw('CASE WHEN last_accessed_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('last_accessed_at')->orderByDesc('lesson_progress.id')->get();

        // Grouped by course, keyed by curriculum item, most recently accessed first (same as groupBy + keyBy).
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->getAttribute('course_id')][(int) $row->curriculum_item_id] = $row;
        }

        return collect($grouped)->map(static fn (array $byItem): Collection => collect($byItem));
    }

    /**
     * @param  Collection<int, LessonProgress>  $rows
     */
    public static function for(User $learner, Course $course, Collection $rows, ?CourseProgress $stored = null): CourseProgress
    {
        ['done' => $done, 'total' => $total] = self::counts($learner, [$course->id])->get($course->id, ['done' => 0, 'total' => 0]);
        $last = $rows->first();

        return ($stored ?? new CourseProgress(['learner_id' => $learner->id, 'course_id' => $course->id]))->forceFill([
            'percent' => $total === 0 ? 0 : (int) floor($done / $total * 100),
            'completed_required' => $done,
            'total_required' => $total,
            'last_item_id' => $last?->curriculum_item_id,
            'last_position_seconds' => $last->position_seconds ?? 0,
            'last_activity_at' => $last?->last_accessed_at,
            'completed_at' => $total > 0 && $done === $total ? ($stored->completed_at ?? now()) : null,
        ]);
    }
}
