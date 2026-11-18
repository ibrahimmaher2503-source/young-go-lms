<?php

declare(strict_types=1);

namespace App\Domain\Seo;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Meta tags, hreflang and structured data (CollectionPage with an ItemList of the listed courses, BreadcrumbList) for a public
 * category landing page. Page 2 and later, or a category with no courses, is noindex and canonical to the base page.
 *
 * @phpstan-import-type Seo from PageSeo
 */
final class CategorySeo
{
    /**
     * @param  LengthAwarePaginator<int, Course>  $courses  the page of published courses shown
     * @return Seo
     */
    public static function for(Category $category, LengthAwarePaginator $courses): array
    {
        $locale = app()->getLocale();
        $name = (string) $category->getTranslation('name', $locale);
        $title = PageSeo::first($category->getTranslation('seo_title', $locale, false), $name);
        $description = Str::limit(PageSeo::first(
            $category->getTranslation('seo_description', $locale, false),
            trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $category->getTranslation('description', $locale)))),
            $name,
        ), 300, '');
        $canonical = PublicUrl::category($category, $locale);
        $urls = [];
        foreach (PublicUrl::locales() as $each) {
            $urls[$each] = PublicUrl::category($category, $each);
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
            ['label' => (string) __('catalog.title'), 'url' => PublicUrl::catalog($locale)],
            ['label' => $name, 'url' => $canonical],
        ]);

        $robots = $courses->currentPage() > 1 || $courses->total() === 0 ? 'noindex, follow' : 'index, follow';

        return PageSeo::make($title, $description, $canonical, $urls, $jsonld, [$breadcrumbs], (string) $category->getTranslation('seo_keywords', $locale, false), 'website', $robots);
    }
}
