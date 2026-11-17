<?php

declare(strict_types=1);

namespace App\Domain\Progress\Actions;

use App\Domain\Entitlements\EntitlementService;
use App\Domain\Progress\LessonProgress;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of lesson progress. The server decides what counts: a heartbeat carries a position, but the furthest
 * point can only advance about as fast as real time passes, so seeking to the end or sending a made-up number never
 * completes a lesson. Completion follows config('learning.progress') per lesson type.
 */
final class RecordProgress
{
    public function __construct(private readonly EntitlementService $access, private readonly RecalculateCourseProgress $recalculate) {}

    public function heartbeat(User $learner, CurriculumItem $item, int $position): LessonProgress
    {
        $lesson = $this->lessonFor($learner, $item);
        $position = max(0, $position);

        $progress = DB::transaction(function () use ($learner, $item, $lesson, $position): LessonProgress {
            $row = LessonProgress::query()->where('learner_id', $learner->getKey())->where('curriculum_item_id', $item->getKey())->lockForUpdate()->first()
                ?? new LessonProgress(['learner_id' => $learner->getKey(), 'curriculum_item_id' => $item->getKey()]);

            $duration = $this->duration($lesson);
            if ($duration !== null) {
                $position = min($position, $duration);
            }

            $now = now();
            $furthest = (int) $row->furthest_seconds;
            if ($row->last_accessed_at !== null) {
                $elapsed = max(0, (int) $row->last_accessed_at->diffInSeconds($now));
                $allowed = $furthest + (int) ceil($elapsed * (float) config('learning.progress.max_speed')) + (int) config('learning.progress.slack_seconds');
            } else {
                $allowed = $furthest + (int) config('learning.progress.slack_seconds');
            }
            $furthest = max($furthest, min($position, $allowed));

            $row->forceFill([
                'position_seconds' => min($position, $allowed), 'furthest_seconds' => $furthest, 'last_accessed_at' => $now,
                'percent' => $duration === null || $duration === 0 ? (int) $row->percent : (int) min(100, floor($furthest / $duration * 100)),
                'status' => $row->status ?? LessonProgress::IN_PROGRESS,
            ]);

            if ($row->status !== LessonProgress::COMPLETED && $this->completesByWatching($lesson, (int) $row->percent, $duration)) {
                $row->forceFill(['status' => LessonProgress::COMPLETED, 'completed_at' => $now, 'completion_source' => 'auto']);
            }
            $row->save();

            return $row;
        });

        ($this->recalculate)($learner, $item->course);
        $this->access->flush(); // a finished lesson can open the next one in a sequential course

        return $progress;
    }

    /** A manual "done" mark, only where the rules allow it for this lesson type. */
    public function markComplete(User $learner, CurriculumItem $item): LessonProgress
    {
        $lesson = $this->lessonFor($learner, $item);
        $duration = $this->duration($lesson);

        $manual = in_array($lesson->type, (array) config('learning.progress.manual_complete_types'), true)
            || (in_array($lesson->type, ['video', 'audio'], true) && ($duration === null || $duration === 0) && (bool) config('learning.progress.manual_when_duration_unknown'));
        if (! $manual) {
            throw ValidationException::withMessages(['complete' => __('learn.cannot_mark_complete')]);
        }

        $progress = DB::transaction(function () use ($learner, $item): LessonProgress {
            $row = LessonProgress::query()->where('learner_id', $learner->getKey())->where('curriculum_item_id', $item->getKey())->lockForUpdate()->first()
                ?? new LessonProgress(['learner_id' => $learner->getKey(), 'curriculum_item_id' => $item->getKey()]);
            if ($row->status !== LessonProgress::COMPLETED) {
                $row->forceFill(['status' => LessonProgress::COMPLETED, 'percent' => 100, 'completed_at' => now(), 'completion_source' => 'manual']);
            }
            $row->forceFill(['last_accessed_at' => now()])->save();

            return $row;
        });

        ($this->recalculate)($learner, $item->course);
        $this->access->flush();

        return $progress;
    }

    private function lessonFor(User $learner, CurriculumItem $item): Lesson
    {
        if (! $this->access->canAccessItem($learner, $item)->allowed) {
            throw new AuthorizationException;
        }

        $lesson = $item->itemable;
        if (! $lesson instanceof Lesson) {
            throw ValidationException::withMessages(['item' => __('learn.cannot_mark_complete')]);
        }

        return $lesson;
    }

    private function duration(Lesson $lesson): ?int
    {
        $seconds = $lesson->media->duration_seconds ?? $lesson->duration_seconds;

        return $seconds !== null && $seconds > 0 ? (int) $seconds : null;
    }

    private function completesByWatching(Lesson $lesson, int $percent, ?int $duration): bool
    {
        return in_array($lesson->type, ['video', 'audio'], true) && $duration !== null && $percent >= (int) config('learning.progress.complete_at_percent');
    }
}
