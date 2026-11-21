<?php

declare(strict_types=1);

namespace App\Domain\Seo;

use App\Models\CmsPage;
use Illuminate\Support\Str;

/**
 * Meta data for one public page: title, description, robots, canonical, hreflang alternates, Open Graph and JSON-LD.
 * Read by x-seo.head. CourseSeo builds on make(); CMS pages and the home page use page() and home().
 *
 * @phpstan-type Seo array{title: string, description: string, keywords: string, robots: string, canonical: string, alternates: array<string, string>, og: array<string, string>, og_alternate: list<string>, jsonld: array<string, mixed>, extra_jsonld: list<array<string, mixed>>}
 */
final class PageSeo
{
    /**
     * @param  array<string, string>  $urls  locale => URL of this page in each language it exists in
     * @param  array<string, mixed>  $jsonld
     * @param  list<array<string, mixed>>  $extra
     * @return Seo
     */
    public static function make(string $title, string $description, string $canonical, array $urls, array $jsonld = [], array $extra = [], string $keywords = '', string $type = 'website', string $robots = 'index, follow'): array
    {
        $locale = app()->getLocale();

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'robots' => $robots,
            'canonical' => $canonical,
            'alternates' => PublicUrl::alternates($urls),
            'og' => [
                'og:type' => $type,
                'og:title' => $title,
                'og:description' => $description,
                'og:url' => $canonical,
                'og:locale' => self::ogLocale($locale),
                'og:site_name' => (string) __('app.name'),
            ],
            'og_alternate' => array_values(array_map(self::ogLocale(...), array_diff(array_keys($urls), [$locale]))),
            'jsonld' => $jsonld,
            'extra_jsonld' => $extra,
        ];
    }

    /**
     * CMS page: per-locale SEO fields win, otherwise the title and the start of the body.
     *
     * @return Seo
     */
    public static function page(CmsPage $page): array
    {
        $locale = app()->getLocale();
        $title = self::first($page->getTranslation('seo_title', $locale, false), $page->getTranslation('title', $locale));
        $body = (string) $page->getTranslation('body', $locale);
        if ($page->seed_key === 'privacy') {
            $body = preg_replace('/^##[ \t]+(?=\S)/mu', '', $body) ?? $body;
        }
        $description = Str::limit(self::first(
            $page->getTranslation('seo_description', $locale, false),
            trim((string) preg_replace('/\s+/u', ' ', strip_tags($body))),
        ), 300, '');
        $urls = [];
        foreach (PublicUrl::localesWithTitle($page) as $each) {
            $urls[$each] = PublicUrl::page($page, $each);
        }

        return self::make($title, $description, PublicUrl::page($page, $locale), $urls, [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'description' => $description,
            'url' => PublicUrl::page($page, $locale),
            'inLanguage' => $locale,
        ]);
    }

    /**
     * Home page: Organization and WebSite structured data. No logo until the owner supplies branding (O-027).
     *
     * @return Seo
     */
    public static function home(): array
    {
        $locale = app()->getLocale();
        $name = (string) __('app.name');
        $urls = [];
        foreach (PublicUrl::locales() as $each) {
            $urls[$each] = PublicUrl::home($each);
        }

        return self::make($name, (string) __('seo.home_description'), PublicUrl::home($locale), $urls, [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'url' => PublicUrl::home($locale),
        ], [[
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $name,
            'url' => PublicUrl::home($locale),
            'inLanguage' => $locale,
        ]]);
    }

    /**
     * @param  list<array{label: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(static fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['label'],
                'item' => $crumb['url'],
            ], $crumbs, array_keys($crumbs)),
        ];
    }

    public static function ogLocale(string $locale): string
    {
        return $locale === 'ar' ? 'ar_EG' : 'en_US';
    }

    public static function first(?string ...$values): string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }
}
