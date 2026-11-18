<?php

declare(strict_types=1);

namespace App\Domain\Seo;

use App\Models\Course;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Meta tags, hreflang and structured data (CollectionPage with an ItemList, BreadcrumbList) for the public course catalog.
 * Only the plain first page is indexable. A search, any filter, a sort order, page 2 or later, or an empty result is
 * `noindex, follow` and its canonical is the base catalog, so filter combinations never compete with the catalog itself.
 *
 * @phpstan-import-type Seo from PageSeo
 */
final class CatalogSeo
{
    /**
     * @param  LengthAwarePaginator<int, Course>  $courses  the page of published courses shown
     * @param  array<string, mixed>  $filters  the active, validated query parameters (search, filters, sort)
     * @return Seo
     */
    public static function for(LengthAwarePaginator $courses, array $filters): array
    {
        $locale = app()->getLocale();
        $title = (string) __('catalog.title');
        $description = (string) __('seo.catalog_description');
        $canonical = PublicUrl::catalog($locale);
        $urls = [];
        foreach (PublicUrl::locales() as $each) {
            $urls[$each] = PublicUrl::catalog($each);
        }

        $items = [];
        foreach ($courses->items() as $index => $course) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => ($courses->firstItem() ?? 1) + $index,
                'url' => PublicUrl::course($course, $locale),
                'name' => (string) $course->getTranslation('title', $locale),
            ];
        }

        $jsonld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $title,
            'description' => $description,
            'url' => $canonical,
            'inLanguage' => $locale,
            'isPartOf' => ['@type' => 'WebSite', 'name' => (string) __('app.name'), 'url' => PublicUrl::home($locale)],
            'mainEntity' => $items === [] ? null : ['@type' => 'ItemList', 'numberOfItems' => $courses->total(), 'itemListElement' => $items],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $breadcrumbs = PageSeo::breadcrumbs([
            ['label' => (string) __('app.name'), 'url' => PublicUrl::home($locale)],
            ['label' => $title, 'url' => $canonical],
        ]);

        return PageSeo::make($title, $description, $canonical, $urls, $jsonld, [$breadcrumbs], '', 'website', self::robots($courses, $filters));
    }

    /**
     * @param  LengthAwarePaginator<int, Course>  $courses
     * @param  array<string, mixed>  $filters
     */
    public static function robots(LengthAwarePaginator $courses, array $filters): string
    {
        $plain = $filters === [] && $courses->currentPage() === 1 && $courses->total() > 0;

        return $plain ? 'index, follow' : 'noindex, follow';
    }
}
