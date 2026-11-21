<?php

declare(strict_types=1);

namespace App\Domain\Seo;

use App\Models\InstructorProfile;
use Illuminate\Support\Str;

/**
 * Meta tags, hreflang and Person structured data for a public instructor page. Only approved profile fields go in: the public
 * name, headline, bio excerpt, avatar and disciplines. Never the account email, phone, username or legal names.
 *
 * @phpstan-import-type Seo from PageSeo
 */
final class InstructorSeo
{
    /** @return Seo */
    public static function for(InstructorProfile $profile, int $page = 1): array
    {
        $locale = app()->getLocale();
        $name = $profile->publicName($locale);
        $headline = $profile->publicHeadline($locale);
        $bio = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $profile->getTranslation('bio', $locale, false))));
        $title = PageSeo::first($profile->getTranslation('seo_title', $locale, false), $name.($headline !== '' ? ' - '.$headline : ''));
        $description = Str::limit(PageSeo::first($profile->getTranslation('seo_description', $locale, false), $bio, $headline, $name), 300, '');
        $canonical = PublicUrl::instructor($profile, $locale);
        $urls = [];
        foreach (PublicUrl::locales() as $each) {
            $urls[$each] = PublicUrl::instructor($profile, $each);
        }
        $avatar = $profile->avatarUrl();
        $image = $avatar === null ? null : url($avatar);
        $disciplines = $profile->disciplines->map(static fn ($category): string => (string) $category->getTranslation('name', $locale))->filter()->values()->all();

        $jsonld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $name,
            'url' => $canonical,
            'description' => $bio !== '' ? Str::limit($bio, 300, '') : null,
            'jobTitle' => $headline !== '' ? $headline : null,
            'image' => $image,
            'knowsAbout' => $disciplines === [] ? null : $disciplines,
            'worksFor' => ['@type' => 'Organization', 'name' => (string) __('app.name'), 'url' => PublicUrl::home($locale)],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $breadcrumbs = PageSeo::breadcrumbs([
            ['label' => (string) __('app.name'), 'url' => PublicUrl::home($locale)],
            ['label' => $name, 'url' => $canonical],
        ]);

        $seo = PageSeo::make($title, $description, $canonical, $urls, $jsonld, [$breadcrumbs], '', 'profile', $page > 1 ? 'noindex, follow' : 'index, follow');
        if ($image !== null) {
            $seo['og']['og:image'] = $image;
        }

        return $seo;
    }
}
