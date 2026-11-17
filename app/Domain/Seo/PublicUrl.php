<?php

declare(strict_types=1);

namespace App\Domain\Seo;

use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Models\CmsPage;
use App\Models\Course;
use Illuminate\Support\Facades\Config;

/** The one place that builds locale-prefixed public URLs (ED-018): /ar/..., /en/... Used by meta, hreflang and the sitemap. */
final class PublicUrl
{
    /** @return list<string> */
    public static function locales(): array
    {
        return array_values(array_map(strval(...), Config::array('app.supported_locales')));
    }

    public static function defaultLocale(): string
    {
        return Config::string('seo.default_locale');
    }

    public static function home(string $locale): string
    {
        return url('/'.$locale);
    }

    public static function catalog(string $locale): string
    {
        return url('/'.$locale.'/courses');
    }

    public static function course(Course $course, string $locale): string
    {
        return url('/'.$locale.'/courses/'.rawurlencode(PublicCourseDetail::keyFor($course, $locale)));
    }

    public static function page(CmsPage $page, string $locale): string
    {
        return url('/'.$locale.'/pages/'.rawurlencode($page->slug));
    }

    /**
     * Locales in which the model has its own (non-fallback) title, so hreflang never points at a copy of another language.
     *
     * @return list<string>
     */
    public static function localesWithTitle(CmsPage|Course $model): array
    {
        return array_values(array_filter(
            self::locales(),
            static fn (string $locale): bool => filled($model->getTranslation('title', $locale, false)),
        ));
    }

    /**
     * hreflang map for a set of locale => URL, plus x-default (the default locale's page, or the first one available).
     *
     * @param  array<string, string>  $urls
     * @return array<string, string>
     */
    public static function alternates(array $urls): array
    {
        if ($urls === []) {
            return [];
        }

        $alternates = $urls;
        $alternates['x-default'] = $urls[self::defaultLocale()] ?? reset($urls);

        return $alternates;
    }
}
