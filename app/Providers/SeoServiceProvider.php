<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Seo\Actions\BuildSitemap;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Support\ServiceProvider;

/** Drops the cached sitemap whenever public content is saved or deleted, so a publish shows up at once (T-P2-06). */
final class SeoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $forget = static fn () => app(BuildSitemap::class)->forget();

        foreach ([Course::class, CmsPage::class, BlogPost::class, Category::class, InstructorProfile::class] as $model) {
            $model::saved($forget);
            $model::deleted($forget);
        }

        // An instructor joins or leaves the public set when the account status changes; routine saves (last_login_at) are ignored.
        User::saved(static function (User $user) use ($forget): void {
            if ($user->wasChanged('status')) {
                $forget();
            }
        });
    }
}
