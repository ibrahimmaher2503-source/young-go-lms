<?php

declare(strict_types=1);

namespace App\Domain\Seo;

use App\Models\BlogPost;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Meta tags, hreflang, Open Graph and structured data for the public blog: the index (Blog) and an article (Article +
 * BreadcrumbList). Only live posts reach it. Filtered or paged index views are noindex and canonical to the base list.
 *
 * @phpstan-import-type Seo from PageSeo
 */
final class BlogSeo
{
    /** @return Seo */
    public static function index(bool $filtered): array
    {
        $locale = app()->getLocale();
        $title = (string) __('blog.title');
        $description = (string) __('blog.description');
        $urls = [];
        foreach (PublicUrl::locales() as $each) {
            $urls[$each] = PublicUrl::blog($each);
        }

        return PageSeo::make($title, $description, PublicUrl::blog($locale), $urls, [
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            'name' => $title,
            'description' => $description,
            'url' => PublicUrl::blog($locale),
            'inLanguage' => $locale,
        ], [], '', 'website', $filtered ? 'noindex, follow' : 'index, follow');
    }

    /** @return Seo */
    public static function post(BlogPost $post): array
    {
        $locale = app()->getLocale();
        $title = PageSeo::first($post->getTranslation('seo_title', $locale, false), $post->getTranslation('title', $locale));
        $description = Str::limit(PageSeo::first(
            $post->getTranslation('seo_description', $locale, false),
            $post->getTranslation('excerpt', $locale),
            trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $post->getTranslation('body', $locale)))),
        ), 300, '');
        $canonical = PublicUrl::blogPost($post, $locale);
        $urls = [];
        foreach (PublicUrl::localesWithTitle($post) as $each) {
            $urls[$each] = PublicUrl::blogPost($post, $each);
        }
        $urls[$locale] ??= $canonical;
        $image = filled($post->featured_image) ? url(Storage::disk('public')->url((string) $post->featured_image)) : null;
        $author = $post->author?->display_name ?: $post->author?->name;

        $jsonld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => Str::limit($title, 110, ''),
            'description' => $description,
            'url' => $canonical,
            'mainEntityOfPage' => $canonical,
            'inLanguage' => $locale,
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at?->toAtomString(),
            'image' => $image,
            'author' => filled($author) ? ['@type' => 'Person', 'name' => $author] : null,
            'publisher' => ['@type' => 'Organization', 'name' => (string) __('app.name')],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $breadcrumbs = PageSeo::breadcrumbs([
            ['label' => (string) __('app.name'), 'url' => PublicUrl::home($locale)],
            ['label' => (string) __('blog.title'), 'url' => PublicUrl::blog($locale)],
            ['label' => $title, 'url' => $canonical],
        ]);

        $seo = PageSeo::make($title, $description, $canonical, $urls, $jsonld, [$breadcrumbs], '', 'article');
        if ($image !== null) {
            $seo['og']['og:image'] = $image;
        }

        return $seo;
    }
}
