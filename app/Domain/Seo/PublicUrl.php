<?php

declare(strict_types=1);

namespace App\Domain\Seo;

use App\Domain\Catalog\Queries\PublicCategory;
use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\Queries\PublicBundles;
use App\Domain\Content\Queries\PublicBlog;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Course;
use App\Models\InstructorProfile;
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

    public static function plans(string $locale): string
    {
        return url('/'.$locale.'/plans');
    }

    public static function bundle(Bundle $bundle, string $locale): string
    {
        return url('/'.$locale.'/bundles/'.rawurlencode(PublicBundles::keyFor($bundle, $locale)));
    }

    public static function page(CmsPage $page, string $locale): string
    {
        return url('/'.$locale.'/pages/'.rawurlencode($page->slug));
    }

    public static function blog(string $locale): string
    {
        return url('/'.$locale.'/blog');
    }

    public static function blogPost(BlogPost $post, string $locale): string
    {
        return url('/'.$locale.'/blog/'.rawurlencode(PublicBlog::keyFor($post, $locale)));
    }

    public static function category(Category $category, string $locale): string
    {
        return url('/'.$locale.'/categories/'.rawurlencode(PublicCategory::keyFor($category, $locale)));
    }

    public static function instructor(InstructorProfile $profile, string $locale): string
    {
        return url('/'.$locale.'/instructors/'.rawurlencode(PublicInstructors::keyFor($profile, $locale)));
    }

    /**
     * Locales in which the model has its own (non-fallback) title, so hreflang never points at a copy of another language.
     *
     * @return list<string>
     */
    public static function localesWithTitle(BlogPost|CmsPage|Course $model): array
    {
        return self::localesWith($model, 'title');
    }

    /**
     * Locales in which the model has its own (non-fallback) value for a translatable field.
     *
     * @return list<string>
     */
    public static function localesWith(BlogPost|Category|CmsPage|Course|InstructorProfile $model, string $field): array
    {
        return array_values(array_filter(
            self::locales(),
            static fn (string $locale): bool => filled($model->getTranslation($field, $locale, false)),
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
