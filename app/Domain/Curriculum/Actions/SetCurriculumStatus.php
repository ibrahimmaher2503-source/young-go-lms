<?php

declare(strict_types=1);

namespace App\Domain\Curriculum\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Media\LessonPublishRule;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Bulk publish or unpublish of any selection of items (ED-024). One audit entry per call. */
final class SetCurriculumStatus
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int|string>  $itemIds
     * @return int number of items whose status changed
     */
    public function __invoke(User $actor, Course $course, array $itemIds, string $status): int
    {
        CurriculumAccess::authorize($actor, $course);
        if (! in_array($status, [CurriculumItem::STATUS_DRAFT, CurriculumItem::STATUS_PUBLISHED], true)) {
            throw ValidationException::withMessages(['status' => __('curriculum.stale')]);
        }

        $ids = array_values(array_unique(array_map('intval', $itemIds)));

        return DB::transaction(function () use ($actor, $course, $ids, $status): int {
            $owned = CurriculumOutline::activeItems(CurriculumItem::query()->where('course_id', $course->getKey())->whereKey($ids))->pluck('id')->all();
            if (count($owned) !== count($ids) || $ids === []) {
                throw ValidationException::withMessages(['items' => __('curriculum.stale')]);
            }

            if ($status === CurriculumItem::STATUS_PUBLISHED) {
                LessonPublishRule::assertPublishable(CurriculumItem::query()->whereKey($ids)->with(['itemable', 'course'])->get());
            }

            $changed = CurriculumItem::query()->whereKey($ids)->where('status', '!=', $status)->update(['status' => $status]);
            $event = $status === CurriculumItem::STATUS_PUBLISHED ? 'curriculum.items_published' : 'curriculum.items_unpublished';
            if ($this->audit->record($event, $course, (int) $actor->getKey(), null, ['changed' => $changed], ['item_ids' => $ids]) === null) {
                throw new RuntimeException('Unable to record curriculum audit.');
            }

            return $changed;
        });
    }
}
