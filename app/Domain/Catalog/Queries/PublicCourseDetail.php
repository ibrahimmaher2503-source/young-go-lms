<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everything the public course page shows, read from published data only. A course that is not published
 * (draft, private, archived, suspended, upcoming) is never returned, so the page answers 404 for it (YG-WEB-004).
 */
final class PublicCourseDetail
{
    /** Resolve by the slug of either locale, or the numeric id. */
    public function find(string $key): ?Course
    {
        return Course::query()
            ->where('courses.status', CourseStatus::Published)
            ->where(static function (Builder $match) use ($key): void {
                $match->where('slug_ar', $key)->orWhere('slug_en', $key);
                if (ctype_digit($key)) {
                    $match->orWhere('courses.id', (int) $key);
                }
            })
            ->with(['category', 'instructors' => static fn ($instructors) => $instructors->where('users.status', 'active')])
            ->first();
    }

    /** URL key for a course in the active locale: its slug, falling back to the other locale's slug and then the id. */
    public static function key(Course $course): string
    {
        return self::keyFor($course, app()->getLocale());
    }

    /** URL key for a course in the given locale (used for hreflang and the sitemap). */
    public static function keyFor(Course $course, string $locale): string
    {
        $slugs = [(string) $course->getAttribute('slug_'.$locale), (string) $course->slug_ar, (string) $course->slug_en];

        return collect($slugs)->first(static fn (string $slug): bool => $slug !== '') ?? (string) $course->getKey();
    }

    /**
     * Published sections and items with titles and durations only; the first preview lesson is the one the hero plays.
     *
     * @return array{sections: list<array{id: int, title: string, items: list<array{id: int, title: string, type: string, minutes: int, preview: bool}>}>, lessons: int, minutes: int, preview_item: int|null}
     */
    public function curriculum(Course $course): array
    {
        $sections = [];
        $lessons = 0;
        $minutes = 0;
        $previewItem = null;

        foreach (CurriculumOutline::forStudents($course) as $section) {
            $items = [];
            foreach ($section->items as $item) {
                /** @var CurriculumItem $item */
                $entity = $item->itemable;
                $lesson = $entity instanceof Lesson ? $entity : null;
                $length = $lesson === null ? 0 : (int) round((int) ($lesson->media->duration_seconds ?? $lesson->duration_seconds ?? 0) / 60);
                $preview = $lesson !== null && $lesson->is_preview;
                $lessons += $lesson === null ? 0 : 1;
                $minutes += $length;
                $previewItem ??= $preview ? (int) $item->getKey() : null;
                $items[] = [
                    'id' => (int) $item->getKey(),
                    'title' => (string) ($entity?->getAttribute('title') ?? ''),
                    'type' => $lesson === null ? (string) $item->itemable_type : $lesson->type,
                    'minutes' => $length,
                    'preview' => $preview,
                ];
            }
            $sections[] = ['id' => (int) $section->getKey(), 'title' => (string) $section->title, 'items' => $items];
        }

        return ['sections' => $sections, 'lessons' => $lessons, 'minutes' => $minutes, 'preview_item' => $previewItem];
    }
}
