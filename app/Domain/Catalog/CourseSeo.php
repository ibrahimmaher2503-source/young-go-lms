<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Engagement\ReviewSummary;
use App\Domain\Seo\PageSeo;
use App\Domain\Seo\PublicUrl;
use App\Models\Course;
use App\Models\InstructorProfile;
use Illuminate\Database\Eloquent\Collection;
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
     * Rating markup only from real published reviews (T-P9-02); none until at least one exists.
     *
     * @return array<string, mixed>|null
     */
    private static function aggregateRating(Course $course): ?array
    {
        $summary = ReviewSummary::for($course);

        return $summary['count'] < 1 ? null : [
            '@type' => 'AggregateRating', 'ratingValue' => $summary['average'], 'reviewCount' => $summary['count'], 'bestRating' => 5, 'worstRating' => 1,
        ];
    }

    /**
     * @param  string  $url  the locale-prefixed URL of this page in the active locale; used as canonical unless the course sets its own
     * @param  Collection<int, InstructorProfile>|null  $profiles  public instructor profiles keyed by user id; their name and page URL replace the account name
     * @return Seo
     */
    public static function for(Course $course, string $url, int $lessons = 0, ?Collection $profiles = null): array
    {
        $locale = app()->getLocale();
        $title = PageSeo::first($course->getTranslation('seo_title', $locale, false), $course->getTranslation('title', $locale));
        $description = Str::limit(PageSeo::first(
            $course->getTranslation('seo_description', $locale, false),
            $course->getTranslation('short_description', $locale),
            strip_tags((string) $course->getTranslation('description', $locale)),
        ), 300, '');
        $canonical = filled($course->canonical_url) ? (string) $course->canonical_url : $url;
        $instructors = $course->instructors->map(static function ($user) use ($profiles, $locale): array {
            $profile = $profiles?->get($user->getKey());

            return array_filter([
                '@type' => 'Person',
                'name' => $profile?->publicName($locale) ?: (string) $user->name,
                'url' => $profile === null ? null : PublicUrl::instructor($profile, $locale),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        })->filter(static fn (array $person): bool => isset($person['name']))->values();

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
            'instructor' => $instructors->isEmpty() ? null : $instructors->all(),
            'numberOfLessons' => $lessons > 0 ? $lessons : null,
            'aggregateRating' => self::aggregateRating($course),
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
