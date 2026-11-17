<?php

declare(strict_types=1);

namespace App\Domain\Progress\Queries;

use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Progress\LessonProgress;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;

/** One learner's lesson-by-lesson state inside one course, for the instructors who run it. Reads only; nothing is written. */
final class LearnerCourseDetail
{
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
