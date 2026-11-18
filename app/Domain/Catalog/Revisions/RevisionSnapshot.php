<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions;

use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Model;

/**
 * The revisable content of a course as plain data: the editable course fields and the active curriculum (sections with
 * their items, in learner order). Ids are the live row ids, so an approved revision updates rows in place and learner
 * progress and access (which hang on those ids) are never disturbed.
 */
final class RevisionSnapshot
{
    /** Course columns a revision may change. Pricing, access, status, ownership, media, SEO and custom fields stay outside it. */
    public const array COURSE_FIELDS = [
        'title', 'short_description', 'description', 'category_id', 'level', 'content_language', 'min_age', 'max_age', 'faqs', 'requirements', 'outcomes',
    ];

    public const array TRANSLATED = ['title', 'short_description', 'description'];

    /** Item kinds that can be created or edited inside a revision. Live sessions are kept and ordered, never created or removed here. */
    public const array EDITABLE_KINDS = ['lesson', 'quiz', 'assignment'];

    /** @return array{course: array<string, mixed>, sections: list<array<string, mixed>>} */
    public static function capture(Course $course): array
    {
        $fields = [];
        foreach (self::COURSE_FIELDS as $field) {
            $fields[$field] = in_array($field, self::TRANSLATED, true) ? $course->getTranslations($field) : $course->getAttribute($field);
        }

        $sections = [];
        foreach (CurriculumOutline::forManager($course) as $section) {
            $items = [];
            foreach ($section->items as $item) {
                $entity = $item->itemable;
                if (! $entity instanceof Model) {
                    continue;
                }
                $items[] = [
                    'id' => (int) $item->getKey(), 'kind' => (string) $item->itemable_type,
                    'title' => self::translations($entity, 'title'), 'description' => self::translations($entity, 'description'),
                    'lesson_type' => $entity instanceof Lesson ? $entity->type : null, 'status' => $item->status,
                ];
            }
            $sections[] = ['id' => (int) $section->getKey(), 'title' => $section->getTranslations('title'), 'published' => (bool) $section->published, 'items' => $items];
        }

        return ['course' => $fields, 'sections' => $sections];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, array<string, mixed>> items keyed by id
     */
    public static function itemsById(array $snapshot): array
    {
        $items = [];
        foreach ((array) ($snapshot['sections'] ?? []) as $section) {
            foreach ((array) ($section['items'] ?? []) as $item) {
                if (($item['id'] ?? null) !== null) {
                    $items[(int) $item['id']] = $item + ['section_id' => $section['id'] ?? null];
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, array<string, mixed>> sections keyed by id
     */
    public static function sectionsById(array $snapshot): array
    {
        $sections = [];
        foreach ((array) ($snapshot['sections'] ?? []) as $section) {
            if (($section['id'] ?? null) !== null) {
                $sections[(int) $section['id']] = $section;
            }
        }

        return $sections;
    }

    /** The text of a translated value in the viewer's language, falling back to the other one. */
    public static function label(mixed $translations): string
    {
        $translations = (array) $translations;
        $locale = app()->getLocale();
        $text = $translations[$locale] ?? $translations[$locale === 'ar' ? 'en' : 'ar'] ?? '';

        return is_string($text) ? $text : '';
    }

    /** @return array<string, string> */
    private static function translations(Model $entity, string $attribute): array
    {
        if (method_exists($entity, 'getTranslations')) {
            /** @var array<string, string> $all */
            $all = $entity->getTranslations($attribute);

            return $all;
        }

        $value = $entity->getAttribute($attribute);

        return is_string($value) && $value !== '' ? [app()->getLocale() => $value] : [];
    }
}
