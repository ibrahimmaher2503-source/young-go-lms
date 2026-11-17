<?php

declare(strict_types=1);

namespace App\Domain\Progress\Actions;

use App\Domain\Entitlements\EntitlementService;
use App\Domain\Progress\LessonProgress;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Marks a quiz (or later an assignment) item as done for a learner, so the sequence rule that opens the next items can
 * see it. The assessment module decides when (submitted, or passed); this only records it, once, and never un-completes.
 */
final class CompleteAssessmentItem
{
    public function __construct(private readonly EntitlementService $access) {}

    public function __invoke(User $learner, CurriculumItem $item, string $source): LessonProgress
    {
        $row = DB::transaction(function () use ($learner, $item, $source): LessonProgress {
            $row = LessonProgress::query()->where('learner_id', $learner->getKey())->where('curriculum_item_id', $item->getKey())->lockForUpdate()->first()
                ?? new LessonProgress(['learner_id' => $learner->getKey(), 'curriculum_item_id' => $item->getKey()]);
            if ($row->status !== LessonProgress::COMPLETED) {
                $row->forceFill(['status' => LessonProgress::COMPLETED, 'percent' => 100, 'completed_at' => now(), 'completion_source' => $source, 'last_accessed_at' => now()])->save();
            }

            return $row;
        });

        $this->access->flush(); // the next item may have just opened

        return $row;
    }
}
