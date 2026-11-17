<?php

declare(strict_types=1);

namespace App\Domain\Progress\Queries;

use App\Domain\Entitlements\LearnerCourses;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** One learner's courses with state and progress: what "my courses" and a parent's per-child summary both show. */
final class LearnerCourseRows
{
    public function __construct(private readonly ContinueLearning $continue) {}

    /** @return Collection<int, array{course: Course, access: string, ends_at: Carbon|null, source: string, status: string, percent: int, done: int, total: int, continue: array{item_id: int, position_seconds: int}|null}> */
    public function for(User $learner): Collection
    {
        // @phpstan-ignore return.type (the merged row shape is correct; Collection templates are invariant)
        return LearnerCourses::for($learner)->each(fn (array $row) => $row['course']->loadMissing(['instructors', 'category']))->map(function (array $row) use ($learner): array {
            $active = $row['access'] === 'active';
            $progress = $active ? $this->continue->progress($learner, $row['course']) : null;
            $status = match (true) {
                ! $active => 'expired',
                $progress?->completed_at !== null => 'completed',
                default => 'in_progress',
            };

            return $row + [
                'status' => $status,
                'percent' => $progress->percent ?? 0,
                'done' => $progress->completed_required ?? 0,
                'total' => $progress->total_required ?? 0,
                'continue' => $active ? $this->continue->for($learner, $row['course']) : null,
            ];
        });
    }
}
