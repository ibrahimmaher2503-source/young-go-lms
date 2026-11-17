<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Seo\Actions\BuildSitemap;
use App\Models\CmsPage;
use App\Models\Course;
use Illuminate\Support\ServiceProvider;

/** Drops the cached sitemap whenever public content is saved or deleted, so a publish shows up at once (T-P2-06). */
final class SeoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $forget = static fn () => app(BuildSitemap::class)->forget();

        foreach ([Course::class, CmsPage::class] as $model) {
            $model::saved($forget);
            $model::deleted($forget);
        }
    }
}
