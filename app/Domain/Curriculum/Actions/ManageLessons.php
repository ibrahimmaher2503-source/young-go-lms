<?php

declare(strict_types=1);

namespace App\Domain\Curriculum\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\Revisions\RevisionAccess;
use App\Domain\Content\RichContent;
use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Library\Actions\AttachLibraryItem;
use App\Domain\Library\LibraryAccess;
use App\Domain\Media\LessonPublishRule;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Create, edit and archive lessons. A new lesson always starts as a draft at the end of its section. */
final class ManageLessons
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function add(User $actor, CourseSection $section, array $data): CurriculumItem
    {
        CurriculumAccess::authorizeStructure($actor, $section->course, CurriculumAccess::CREATE);
        if ($section->archived_at !== null) {
            throw ValidationException::withMessages(['section' => __('curriculum.section_archived')]);
        }
        $attributes = $this->attributes($data);

        return DB::transaction(function () use ($actor, $section, $attributes): CurriculumItem {
            CourseSection::query()->whereKey($section->getKey())->lockForUpdate()->first();
            $lesson = Lesson::query()->create($attributes);
            $sort = (int) CurriculumItem::query()->where('section_id', $section->getKey())->max('sort') + 1;

            $item = CurriculumItem::query()->create([
                'course_id' => $section->course_id, 'section_id' => $section->getKey(), 'sort' => $sort,
                'itemable_type' => 'lesson', 'itemable_id' => $lesson->getKey(), 'status' => CurriculumItem::STATUS_DRAFT,
            ]);
            $this->attachRichImages($actor, $lesson, $attributes['content']['content'] ?? []);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  (Closure(Lesson): void)|null  $saveMedia
     */
    public function update(User $actor, Lesson $lesson, array $data, ?Closure $saveMedia = null): Lesson
    {
        $item = $this->itemFor($lesson);
        CurriculumAccess::authorize($actor, $item->course);
        $attributes = $this->attributes($data);
        // T-P3-07: a changed title or description is a content change; it goes through a revision where one is required.
        foreach (['title', 'description'] as $text) {
            if (array_key_exists($text, $attributes) && $attributes[$text] != $lesson->getTranslations($text)) {
                RevisionAccess::assertLiveEditable($actor, $item->course);
            }
        }

        return DB::transaction(function () use ($actor, $item, $lesson, $attributes, $saveMedia): Lesson {
            $currentItem = CurriculumItem::query()->lockForUpdate()->findOrFail($item->getKey());
            $this->attachRichImages($actor, $lesson, $attributes['content']['content'] ?? []);
            $lesson->update($attributes);
            $saveMedia?->__invoke($lesson);
            if ($currentItem->status === CurriculumItem::STATUS_PUBLISHED) {
                LessonPublishRule::assertPublishable([$currentItem->load('itemable.media')]);
            }

            return $lesson;
        });
    }

    /** Archived lessons disappear for students and stay for attempts, submissions and history. Audited. */
    public function archive(User $actor, Lesson $lesson): void
    {
        $item = $this->itemFor($lesson);
        CurriculumAccess::authorizeStructure($actor, $item->course, CurriculumAccess::DELETE);

        DB::transaction(function () use ($actor, $lesson): void {
            $lesson->update(['archived_at' => now()]);
            if ($this->audit->record('curriculum.lesson_archived', $lesson, (int) $actor->getKey()) === null) {
                throw new RuntimeException('Unable to record curriculum audit.');
            }
        });
    }

    /** @param list<array<string, mixed>> $nodes */
    private function attachRichImages(User $actor, Lesson $lesson, array $nodes): void
    {
        foreach ($nodes as $node) {
            if (($node['attrs']['id'] ?? null) === 'lesson-library-image') {
                $id = (int) $node['attrs']['config']['library_item_id'];
                $asset = LibraryAccess::visibleTo($actor)->where('kind', 'image')->find($id);
                if ($asset === null) {
                    throw ValidationException::withMessages(['content' => __('content.image_unavailable')]);
                }
                if (! $lesson->resources()->where('library_item_id', $id)->exists()) {
                    app(AttachLibraryItem::class)->asResource($actor, $asset, $lesson);
                }
            }
            $this->attachRichImages($actor, $lesson, $node['content'] ?? []);
        }
    }

    private function itemFor(Lesson $lesson): CurriculumItem
    {
        $item = $lesson->item()->with('course')->first();
        abort_if($item === null, 404);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $validated = Validator::make($data, [
            'type' => ['sometimes', Rule::in(Lesson::TYPES)],
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:5000'],
            'description.en' => ['nullable', 'string', 'max:5000'],
            'duration_seconds' => ['nullable', 'integer', 'between:0,86400'],
            'is_preview' => ['sometimes', 'boolean'],
        ])->validate();

        $attributes = ['title' => CurriculumAccess::validTitle($data)];
        foreach (['type', 'duration_seconds', 'is_preview'] as $key) {
            if (array_key_exists($key, $validated)) {
                $attributes[$key] = $validated[$key];
            }
        }
        if (array_key_exists('content', $data)) {
            $content = $data['content'];
            $attributes['content'] = $content === null || $content === [] ? null : RichContent::clean($content);
        }
        if (array_key_exists('description', $validated)) {
            $attributes['description'] = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : '', (array) $validated['description']), static fn (string $v): bool => $v !== '');
        }

        return $attributes;
    }
}
