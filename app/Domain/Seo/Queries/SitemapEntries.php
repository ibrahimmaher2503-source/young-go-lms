<?php

declare(strict_types=1);

namespace App\Domain\Seo\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Seo\PublicUrl;
use App\Models\CmsPage;
use App\Models\Course;
use Generator;

/**
 * Every public, indexable page and its language versions. Only approved public content is listed: published courses and
 * published CMS pages plus the home page. Drafts, private, suspended, archived or upcoming courses, learner and staff areas never
 * appear. The catalog listing is noindex, so it is not listed. Categories and blog join when their pages exist (T-P2-07, T-P2-09).
 */
final class SitemapEntries
{
    /** @return Generator<int, array{lastmod: string|null, urls: array<string, string>}> */
    public function all(): Generator
    {
        yield ['lastmod' => null, 'urls' => array_combine(PublicUrl::locales(), array_map(PublicUrl::home(...), PublicUrl::locales()))];

        foreach (Course::query()->where('status', CourseStatus::Published)->orderBy('id')->cursor() as $course) {
            $urls = [];
            foreach (PublicUrl::localesWithTitle($course) as $locale) {
                $urls[$locale] = PublicUrl::course($course, $locale);
            }
            if ($urls !== []) {
                yield ['lastmod' => $course->updated_at?->toAtomString(), 'urls' => $urls];
            }
        }

        foreach (CmsPage::query()->where('status', CmsPage::PUBLISHED)->orderBy('id')->cursor() as $page) {
            $urls = [];
            foreach (PublicUrl::localesWithTitle($page) as $locale) {
                $urls[$locale] = PublicUrl::page($page, $locale);
            }
            if ($urls !== []) {
                yield ['lastmod' => $page->updated_at?->toAtomString(), 'urls' => $urls];
            }
        }
    }
}
