<?php

declare(strict_types=1);

namespace App\Domain\Progress\Actions;

use App\Domain\Progress\LessonProgress;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Historical progress writer for legacy migration. Preserves existing LessonProgress records when newer/higher
 * and recalculates the parent CourseProgress snapshot without weakening live heartbeat anti-seek rules.
 */
final class ImportLegacyProgress
{
    public function __construct(private readonly RecalculateCourseProgress $recalculate) {}

    public function __invoke(
        User $learner,
        CurriculumItem $item,
        bool $completed,
        int $positionSeconds,
        ?CarbonInterface $completedAt = null,
        ?CarbonInterface $lastAccessedAt = null,
    ): LessonProgress {
        $position = max(0, $positionSeconds);
        $duration = $item->itemable instanceof Lesson ? ($item->itemable->media->duration_seconds ?? $item->itemable->duration_seconds) : null;
        if ($duration !== null && $duration > 0) {
            $position = min($position, (int) $duration);
        }

        return DB::transaction(function () use ($learner, $item, $completed, $position, $duration, $completedAt, $lastAccessedAt): LessonProgress {
            $row = LessonProgress::query()
                ->where('learner_id', $learner->getKey())
                ->where('curriculum_item_id', $item->getKey())
                ->lockForUpdate()
                ->first() ?? new LessonProgress([
                    'learner_id' => $learner->getKey(),
                    'curriculum_item_id' => $item->getKey(),
                ]);

            $furthest = max((int) $row->furthest_seconds, $position);
            $percent = $completed
                ? 100
                : ($duration !== null && $duration > 0 ? (int) min(100, floor($furthest / $duration * 100)) : (int) $row->percent);

            $status = ($row->status === LessonProgress::COMPLETED || $completed)
                ? LessonProgress::COMPLETED
                : LessonProgress::IN_PROGRESS;

            $row->forceFill([
                'status' => $status,
                'position_seconds' => $position,
                'furthest_seconds' => $furthest,
                'percent' => max((int) $row->percent, $percent),
                'completion_source' => $status === LessonProgress::COMPLETED
                    ? ($row->completion_source ?? 'legacy')
                    : $row->completion_source,
                'completed_at' => $status === LessonProgress::COMPLETED
                    ? ($row->completed_at ?? $completedAt ?? now())
                    : null,
                'last_accessed_at' => $lastAccessedAt ?? $row->last_accessed_at ?? now(),
            ])->save();

            ($this->recalculate)($learner, $item->course);

            return $row;
        });
    }
}
