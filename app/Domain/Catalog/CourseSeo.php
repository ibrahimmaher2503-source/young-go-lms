<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Models\Course;
use Illuminate\Support\Str;

/**
 * Meta tags, Open Graph and Course structured data for one public course page. Per-locale SEO fields win; otherwise the
 * title and short description are used. Only public, published data goes in (hreflang and sitemap follow in T-P2-06).
 */
final class CourseSeo
{
    /** @return array{title: string, description: string, keywords: string, canonical: string, og: array<string, string>, jsonld: array<string, mixed>} */
    public static function for(Course $course, string $url, int $lessons = 0): array
    {
        $locale = app()->getLocale();
        $title = self::first($course->getTranslation('seo_title', $locale, false), $course->getTranslation('title', $locale));
        $description = Str::limit(self::first(
            $course->getTranslation('seo_description', $locale, false),
            $course->getTranslation('short_description', $locale),
            strip_tags((string) $course->getTranslation('description', $locale)),
        ), 300, '');
        $canonical = filled($course->canonical_url) ? (string) $course->canonical_url : $url;
        $instructors = $course->instructors->pluck('name')->filter()->values();

        $jsonld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $title,
            'description' => $description,
            'url' => $canonical,
            'inLanguage' => $course->content_language,
            'educationalLevel' => $course->level,
            'provider' => ['@type' => 'Organization', 'name' => (string) __('app.name')],
            'instructor' => $instructors->isEmpty() ? null : $instructors->map(static fn (string $name): array => ['@type' => 'Person', 'name' => $name])->all(),
            'numberOfLessons' => $lessons > 0 ? $lessons : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => (string) $course->getTranslation('seo_keywords', $locale, false),
            'canonical' => $canonical,
            'og' => [
                'og:type' => 'website',
                'og:title' => $title,
                'og:description' => $description,
                'og:url' => $canonical,
                'og:locale' => $locale === 'ar' ? 'ar_EG' : 'en_US',
                'og:site_name' => (string) __('app.name'),
            ],
            'jsonld' => $jsonld,
        ];
    }

    private static function first(?string ...$values): string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }
}
