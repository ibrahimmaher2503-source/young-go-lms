<?php

declare(strict_types=1);

namespace App\Domain\Seo;

use App\Domain\Commerce\Bundle;
use Illuminate\Support\Str;

/**
 * Meta data for the public plans page and the public bundle page (T-P8-12). Both are indexable only while they list something sellable.
 *
 * @phpstan-import-type Seo from PageSeo
 */
final class OfferSeo
{
    /** @return Seo */
    public static function plans(): array
    {
        $locale = app()->getLocale();
        $title = (string) __('subscriptions.plans_page_title');
        $description = (string) __('subscriptions.plans_page_description');
        $urls = [];
        foreach (PublicUrl::locales() as $each) {
            $urls[$each] = PublicUrl::plans($each);
        }

        return PageSeo::make($title, $description, PublicUrl::plans($locale), $urls, [], [PageSeo::breadcrumbs([
            ['label' => (string) __('app.name'), 'url' => PublicUrl::home($locale)],
            ['label' => $title, 'url' => PublicUrl::plans($locale)],
        ])]);
    }

    /** @return Seo */
    public static function bundle(Bundle $bundle): array
    {
        $locale = app()->getLocale();
        $name = (string) $bundle->getTranslation('name', $locale);
        $description = Str::limit((string) __('subscriptions.bundle_description').' '.$name, 300, '');
        $urls = [];
        foreach (PublicUrl::locales() as $each) {
            $urls[$each] = PublicUrl::bundle($bundle, $each);
        }

        return PageSeo::make($name, $description, PublicUrl::bundle($bundle, $locale), $urls, [], [PageSeo::breadcrumbs([
            ['label' => (string) __('app.name'), 'url' => PublicUrl::home($locale)],
            ['label' => $name, 'url' => PublicUrl::bundle($bundle, $locale)],
        ])]);
    }
}
