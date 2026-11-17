<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Seo\PageSeo;
use App\Domain\Seo\PublicUrl;
use App\Models\Course;
use Illuminate\Support\Str;

/**
 * Meta tags, hreflang, Open Graph and structured data (Course, BreadcrumbList) for one public course page. Per-locale SEO
 * fields win; otherwise the title and short description are used. Only public, published data goes in.
 *
 * @phpstan-import-type Seo from PageSeo
 */
final class CourseSeo
{
    /**
     * @param  string  $url  the locale-prefixed URL of this page in the active locale; used as canonical unless the course sets its own
     * @return Seo
     */
    public static function for(Course $course, string $url, int $lessons = 0): array
    {
        $locale = app()->getLocale();
        $title = PageSeo::first($course->getTranslation('seo_title', $locale, false), $course->getTranslation('title', $locale));
        $description = Str::limit(PageSeo::first(
            $course->getTranslation('seo_description', $locale, false),
            $course->getTranslation('short_description', $locale),
            strip_tags((string) $course->getTranslation('description', $locale)),
        ), 300, '');
        $canonical = filled($course->canonical_url) ? (string) $course->canonical_url : $url;
        $instructors = $course->instructors->pluck('name')->filter()->values();

        $urls = [];
        foreach (PublicUrl::localesWithTitle($course) as $each) {
            $urls[$each] = PublicUrl::course($course, $each);
        }
        $urls[$locale] ??= $url;

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

        $breadcrumbs = PageSeo::breadcrumbs([
            ['label' => (string) __('app.name'), 'url' => PublicUrl::home($locale)],
            ['label' => (string) __('catalog.title'), 'url' => PublicUrl::catalog($locale)],
            ['label' => $title, 'url' => $canonical],
        ]);

        return PageSeo::make(
            $title,
            $description,
            $canonical,
            $urls,
            $jsonld,
            [$breadcrumbs],
            (string) $course->getTranslation('seo_keywords', $locale, false),
        );
    }
}
