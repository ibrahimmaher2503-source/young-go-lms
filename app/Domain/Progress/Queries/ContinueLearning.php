<?php

declare(strict_types=1);

namespace App\Domain\Progress\Queries;

use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Progress\LessonProgress;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;

/** Resume the most recent unfinished open item or start with the first available one. */
final class ContinueLearning
{
    public function __construct(private readonly EntitledCurriculumQuery $curriculum) {}

    /**
     * @param  list<array{id: int, title: string, items: list<array{id: int, title: string, type: string, state: string, reason: string|null, unlock_at: string|null, minutes: int}>}>|null  $sections
     * @param  Collection<int, LessonProgress>|null  $progress
     * @return array{item_id: int, position_seconds: int}|null
     */
    public function for(User $learner, Course $course, ?array $sections = null, ?Collection $progress = null): ?array
    {
        $open = collect($sections ?? $this->curriculum->for($learner, $course))
            ->flatMap(fn (array $section): array => $section['items'])
            ->where('state', '!=', 'locked')->keyBy('id');
        if ($open->isEmpty()) {
            return null;
        }

        $progress ??= CourseProgressSnapshot::lessonRows($learner, [$course->id])->get($course->id, collect());
        $last = $progress->first();
        if ($last !== null && $last->status !== LessonProgress::COMPLETED && $open->has($last->curriculum_item_id)) {
            return ['item_id' => (int) $last->curriculum_item_id, 'position_seconds' => (int) $last->position_seconds];
        }

        $next = $open->first(fn (array $item): bool => $progress->get($item['id'])?->status !== LessonProgress::COMPLETED) ?? $open->first();

        return ['item_id' => (int) $next['id'], 'position_seconds' => 0];
    }
}
