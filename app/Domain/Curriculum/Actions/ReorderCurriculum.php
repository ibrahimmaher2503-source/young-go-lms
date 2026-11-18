<?php

declare(strict_types=1);

namespace App\Domain\Curriculum\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Persists a complete section/item order in one transaction. The payload must list every active section and
 * item of the course, so a stale browser tab cannot silently drop or duplicate anything.
 */
final class ReorderCurriculum
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<array{section_id: int|string, items: list<int|string>}> $layout */
    public function __invoke(User $actor, Course $course, array $layout): void
    {
        CurriculumAccess::authorizeStructure($actor, $course);

        // The payload comes from the browser: refuse anything that is not a list of {section_id, items[]} before touching the database.
        Validator::make(['layout' => $layout], [
            'layout' => ['array'],
            'layout.*.section_id' => ['required', 'integer'],
            'layout.*.items' => ['present', 'array'],
            'layout.*.items.*' => ['integer'],
        ])->after(static function ($validator) use ($layout): void {
            if (! array_is_list($layout)) { // @phpstan-ignore function.alreadyNarrowedType (browser input is untrusted)
                $validator->errors()->add('layout', __('curriculum.stale'));
            }
        })->validate();

        DB::transaction(function () use ($actor, $course, $layout): void {
            Course::query()->whereKey($course->getKey())->lockForUpdate()->first();

            $sectionIds = CourseSection::query()->where('course_id', $course->getKey())->whereNull('archived_at')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            $itemIds = CurriculumOutline::activeItems(CurriculumItem::query()->where('course_id', $course->getKey())->whereIn('section_id', $sectionIds))
                ->pluck('id')->map(static fn ($id): int => (int) $id)->all();

            $givenSections = array_map(static fn (array $row): int => (int) $row['section_id'], $layout);
            $givenItems = array_merge(...array_map(static fn (array $row): array => array_map('intval', $row['items']), $layout ?: [['items' => []]]));

            if ($this->differs($sectionIds, $givenSections) || $this->differs($itemIds, $givenItems)) {
                throw ValidationException::withMessages(['layout' => __('curriculum.stale')]);
            }

            foreach ($layout as $position => $row) {
                CourseSection::query()->whereKey((int) $row['section_id'])->update(['sort' => $position + 1]);
                foreach ($row['items'] as $index => $itemId) {
                    CurriculumItem::query()->whereKey((int) $itemId)->update(['section_id' => (int) $row['section_id'], 'sort' => $index + 1]);
                }
            }

            if ($this->audit->record('curriculum.reordered', $course, (int) $actor->getKey(), null, ['sections' => count($layout), 'items' => count($givenItems)]) === null) {
                throw new RuntimeException('Unable to record curriculum audit.');
            }
        });
    }

    /**
     * @param  list<int>  $expected
     * @param  list<int>  $given
     */
    private function differs(array $expected, array $given): bool
    {
        sort($expected);
        sort($given);

        return $expected !== $given;
    }
}
