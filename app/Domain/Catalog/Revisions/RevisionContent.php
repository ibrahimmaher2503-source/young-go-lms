<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions;

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\CourseDescription;
use App\Domain\Content\RichContent;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Validates and normalizes the content a revision proposes. Ids may only name rows of the snapshot the revision started from. */
final class RevisionContent
{
    private const int MAX_ROWS = 30;

    private const int MAX_SECTIONS = 100;

    private const int MAX_ITEMS = 300;

    /**
     * @param  array<string, mixed>  $input  {course: {...}, sections: [{id?, title, published, items: [{id?, kind, title, description, lesson_type, status}]}]}
     * @param  array<string, mixed>  $base  the snapshot the revision started from
     * @return array{course: array<string, mixed>, sections: list<array<string, mixed>>}
     */
    public static function normalize(array $input, array $base): array
    {
        $input['course'] = self::richDescriptions((array) ($input['course'] ?? []));
        $input['sections'] = array_values((array) ($input['sections'] ?? []));

        $row = static fn (int $max): array => ['nullable', 'string', 'max:'.$max];
        $validator = Validator::make($input, [
            'course.title' => ['nullable', 'array'], 'course.title.ar' => $row(200), 'course.title.en' => $row(200),
            'course.short_description' => ['nullable', 'array'], 'course.short_description.ar' => $row(300), 'course.short_description.en' => $row(300),
            'course.description' => ['nullable', 'array'], 'course.description.ar' => $row(10000), 'course.description.en' => $row(10000),
            'course.category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'course.level' => ['required', Rule::in(SaveCourseWizard::LEVELS)],
            'course.content_language' => ['required', Rule::in(SaveCourseWizard::CONTENT_LANGUAGES)],
            'course.min_age' => ['nullable', 'integer', 'between:0,120'],
            'course.max_age' => ['nullable', 'integer', 'between:0,120', 'gte:course.min_age'],
            'course.faqs' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'course.faqs.*.question.ar' => $row(300), 'course.faqs.*.question.en' => $row(300),
            'course.faqs.*.answer.ar' => $row(2000), 'course.faqs.*.answer.en' => $row(2000),
            'course.requirements' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'course.requirements.*.text.ar' => $row(500), 'course.requirements.*.text.en' => $row(500),
            'course.outcomes' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'course.outcomes.*.text.ar' => $row(500), 'course.outcomes.*.text.en' => $row(500),
            'sections' => ['array', 'max:'.self::MAX_SECTIONS],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.title' => ['required', 'array'], 'sections.*.title.ar' => $row(150), 'sections.*.title.en' => $row(150),
            'sections.*.published' => ['nullable', 'boolean'],
            'sections.*.items' => ['nullable', 'array', 'max:'.self::MAX_ITEMS],
            'sections.*.items.*.id' => ['nullable', 'integer'],
            'sections.*.items.*.kind' => ['nullable', Rule::in([...RevisionSnapshot::EDITABLE_KINDS, 'live_session'])],
            'sections.*.items.*.title' => ['nullable', 'array'], 'sections.*.items.*.title.ar' => $row(150), 'sections.*.items.*.title.en' => $row(150),
            'sections.*.items.*.description' => ['nullable', 'array'], 'sections.*.items.*.description.ar' => $row(5000), 'sections.*.items.*.description.en' => $row(5000),
            'sections.*.items.*.lesson_type' => ['nullable', Rule::in(Lesson::TYPES)],
            'sections.*.items.*.status' => ['nullable', Rule::in([CurriculumItem::STATUS_DRAFT, CurriculumItem::STATUS_PUBLISHED])],
        ]);
        $validator->after(static function ($validator) use ($input): void {
            $title = (array) data_get($input, 'course.title', []);
            if (blank($title['ar'] ?? null) && blank($title['en'] ?? null)) {
                $validator->errors()->add('course.title.ar', __('validation.required', ['attribute' => __('courses.name')]));
            }
        });
        $validated = $validator->validate();

        return ['course' => self::course((array) $validated['course']), 'sections' => self::sections((array) ($validated['sections'] ?? []), $base)];
    }

    /**
     * @param  array<string, mixed>  $course
     * @return array<string, mixed>
     */
    private static function richDescriptions(array $course): array
    {
        foreach (['ar', 'en'] as $locale) {
            $description = data_get($course, 'description.'.$locale);
            if (is_array($description)) {
                try {
                    $course['description'][$locale] = $description === [] ? null : (string) RichContent::html(RichContent::clean($description));
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['course.description.'.$locale => $exception->errors()['content'] ?? __('content.invalid')]);
                }
            }
        }

        return $course;
    }

    /**
     * @param  array<string, mixed>  $course
     * @return array<string, mixed>
     */
    private static function course(array $course): array
    {
        $clean = [];
        foreach (['title', 'short_description', 'description'] as $field) {
            $values = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : null, (array) ($course[$field] ?? [])), static fn ($v) => $v !== null && $v !== '');
            $clean[$field] = $field === 'description' ? array_filter(array_map(CourseDescription::clean(...), $values)) : $values;
        }
        $clean['category_id'] = (int) $course['category_id'];
        $clean['level'] = (string) $course['level'];
        $clean['content_language'] = (string) $course['content_language'];
        foreach (['min_age', 'max_age'] as $age) {
            $clean[$age] = blank($course[$age] ?? null) ? null : (int) $course[$age];
        }
        foreach (['faqs', 'requirements', 'outcomes'] as $list) {
            $clean[$list] = self::rows(array_values((array) ($course[$list] ?? [])));
        }

        return $clean;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private static function rows(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $hasText = false;
            foreach ($row as $key => $value) {
                $row[$key] = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : $v, (array) $value), static fn ($v) => $v !== null && $v !== '');
                $hasText = $hasText || $row[$key] !== [];
            }
            if ($hasText) {
                $clean[] = $row;
            }
        }

        return $clean;
    }

    /**
     * @param  array<int, mixed>  $sections
     * @param  array<string, mixed>  $base
     * @return list<array<string, mixed>>
     */
    private static function sections(array $sections, array $base): array
    {
        $baseSections = RevisionSnapshot::sectionsById($base);
        $baseItems = RevisionSnapshot::itemsById($base);
        $seenSections = [];
        $seenItems = [];
        $result = [];

        foreach (array_values($sections) as $index => $section) {
            $section = (array) $section;
            $id = blank($section['id'] ?? null) ? null : (int) $section['id'];
            if ($id !== null && (! isset($baseSections[$id]) || isset($seenSections[$id]))) {
                throw ValidationException::withMessages(['sections.'.$index => __('course_revisions.stale_structure')]);
            }
            $seenSections[$id ?? 'new'.$index] = true;

            $title = self::text((array) ($section['title'] ?? []));
            if ($title === []) {
                throw ValidationException::withMessages(['sections.'.$index.'.title.ar' => __('validation.required', ['attribute' => __('curriculum.title')])]);
            }

            $items = [];
            foreach (array_values((array) ($section['items'] ?? [])) as $position => $item) {
                $item = (array) $item;
                $itemId = blank($item['id'] ?? null) ? null : (int) $item['id'];
                $path = 'sections.'.$index.'.items.'.$position;
                if ($itemId !== null && (! isset($baseItems[$itemId]) || isset($seenItems[$itemId]))) {
                    throw ValidationException::withMessages([$path => __('course_revisions.stale_structure')]);
                }
                if ($itemId !== null) {
                    $seenItems[$itemId] = true;
                }
                $items[] = self::item($item, $itemId === null ? null : $baseItems[$itemId], $path);
            }

            $result[] = [
                'id' => $id, 'title' => $title,
                'published' => array_key_exists('published', $section) ? (bool) $section['published'] : (bool) ($baseSections[$id]['published'] ?? false),
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $existing  the snapshot row of an existing item
     * @return array<string, mixed>
     */
    private static function item(array $item, ?array $existing, string $path): array
    {
        if ($existing !== null) {
            $fixed = $existing['kind'] === 'live_session';

            return [
                'id' => (int) $existing['id'], 'kind' => (string) $existing['kind'],
                'title' => $fixed ? $existing['title'] : self::requiredTitle($item, $path),
                'description' => $fixed ? $existing['description'] : self::text((array) ($item['description'] ?? [])),
                'lesson_type' => $existing['lesson_type'], 'status' => (string) ($item['status'] ?? $existing['status']),
            ];
        }

        $kind = (string) ($item['kind'] ?? 'lesson');
        if (! in_array($kind, RevisionSnapshot::EDITABLE_KINDS, true)) {
            throw ValidationException::withMessages([$path.'.kind' => __('course_revisions.stale_structure')]);
        }

        return [
            'id' => null, 'kind' => $kind, 'title' => self::requiredTitle($item, $path),
            'description' => self::text((array) ($item['description'] ?? [])),
            'lesson_type' => $kind === 'lesson' ? (string) ($item['lesson_type'] ?? 'text') : null,
            'status' => (string) ($item['status'] ?? CurriculumItem::STATUS_DRAFT),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private static function requiredTitle(array $item, string $path): array
    {
        $title = self::text((array) ($item['title'] ?? []));
        if ($title === []) {
            throw ValidationException::withMessages([$path.'.title.ar' => __('validation.required', ['attribute' => __('curriculum.title')])]);
        }

        return $title;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private static function text(array $values): array
    {
        return array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : '', $values), static fn (string $v): bool => $v !== '');
    }
}
