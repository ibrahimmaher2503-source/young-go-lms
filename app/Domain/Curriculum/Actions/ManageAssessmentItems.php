<?php

declare(strict_types=1);

namespace App\Domain\Curriculum\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\CurriculumAccess;
use App\Models\Assignment;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Places a quiz or assignment shell in a section (a draft at the end) and archives it. Authoring itself is P6. */
final class ManageAssessmentItems
{
    public const array KINDS = ['quiz' => Quiz::class, 'assignment' => Assignment::class];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data title.ar / title.en and an optional description */
    public function add(User $actor, CourseSection $section, string $kind, array $data): CurriculumItem
    {
        CurriculumAccess::authorize($actor, $section->course, CurriculumAccess::CREATE);
        if (! array_key_exists($kind, self::KINDS)) {
            throw ValidationException::withMessages(['kind' => __('curriculum.assessment_kind_invalid')]);
        }
        if ($section->archived_at !== null) {
            throw ValidationException::withMessages(['section' => __('curriculum.section_archived')]);
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'array'],
            'title.ar' => ['nullable', 'string', 'max:150', Rule::requiredIf(blank($data['title']['en'] ?? null))],
            'title.en' => ['nullable', 'string', 'max:150', Rule::requiredIf(blank($data['title']['ar'] ?? null))],
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:5000'],
            'description.en' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $model = self::KINDS[$kind];

        return DB::transaction(function () use ($actor, $section, $kind, $model, $validated): CurriculumItem {
            CourseSection::query()->whereKey($section->getKey())->lockForUpdate()->first();
            $entity = $model::query()->create(['title' => array_filter($validated['title']), 'description' => array_filter($validated['description'] ?? [])]);
            $sort = (int) CurriculumItem::query()->where('section_id', $section->getKey())->max('sort') + 1;
            $item = CurriculumItem::query()->create([
                'course_id' => $section->course_id, 'section_id' => $section->getKey(), 'sort' => $sort,
                'itemable_type' => $kind, 'itemable_id' => $entity->getKey(), 'status' => CurriculumItem::STATUS_DRAFT,
            ]);

            if ($this->audit->record('curriculum.'.$kind.'_added', $entity, (int) $actor->getKey()) === null) {
                throw new RuntimeException('Unable to record curriculum audit.');
            }

            return $item;
        });
    }

    public function archive(User $actor, CurriculumItem $item): void
    {
        CurriculumAccess::authorize($actor, $item->course, CurriculumAccess::DELETE);
        $entity = $item->itemable;
        if (! $entity instanceof Quiz && ! $entity instanceof Assignment) {
            abort(404);
        }

        DB::transaction(function () use ($actor, $item, $entity): void {
            $entity->update(['archived_at' => now()]);
            if ($this->audit->record('curriculum.'.$item->itemable_type.'_archived', $entity, (int) $actor->getKey()) === null) {
                throw new RuntimeException('Unable to record curriculum audit.');
            }
        });
    }
}
