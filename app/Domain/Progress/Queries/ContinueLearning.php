<?php

declare(strict_types=1);

namespace App\Domain\Progress\Queries;

use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Progress\Actions\RecalculateCourseProgress;
use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\LessonProgress;
use App\Models\Course;
use App\Models\User;

/** Where to send a learner: the lesson they touched last (with its position) while it is still open, else the first unfinished open one. */
final class ContinueLearning
{
    public function __construct(private readonly EntitledCurriculumQuery $curriculum, private readonly RecalculateCourseProgress $recalculate) {}

    /** @return array{item_id: int, position_seconds: int}|null */
    public function for(User $learner, Course $course): ?array
    {
        $open = collect($this->curriculum->for($learner, $course))->flatMap(fn (array $s): array => $s['items'])->filter(fn (array $i): bool => $i['state'] !== 'locked')->values();
        if ($open->isEmpty()) {
            return null;
        }

        $progress = ($this->recalculate)($learner, $course);
        if ($progress->last_item_id !== null && $open->contains('id', $progress->last_item_id)) {
            $last = LessonProgress::query()->where('learner_id', $learner->getKey())->where('curriculum_item_id', $progress->last_item_id)->first();
            if ($last === null || $last->status !== LessonProgress::COMPLETED) {
                return ['item_id' => (int) $progress->last_item_id, 'position_seconds' => (int) ($last->position_seconds ?? 0)];
            }
        }

        $completed = LessonProgress::query()->where('learner_id', $learner->getKey())->where('status', LessonProgress::COMPLETED)->pluck('curriculum_item_id')->all();
        $next = $open->first(fn (array $i): bool => ! in_array($i['id'], $completed, true)) ?? $open->first();

        return ['item_id' => (int) $next['id'], 'position_seconds' => 0];
    }

    public function progress(User $learner, Course $course): CourseProgress
    {
        return ($this->recalculate)($learner, $course);
    }
}
