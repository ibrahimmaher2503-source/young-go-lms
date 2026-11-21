<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Queries\CatalogQuery;
use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Commerce\Queries\CheckoutOffers;
use App\Domain\Engagement\ReviewSummary;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Seo\PageSeo;
use App\Domain\Shared\Markets\Market;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Course;
use App\Models\HomepageBlock;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

final class CmsController
{
    public function home(CatalogQuery $catalog, CheckoutOffers $offers, PublicInstructors $instructors, PublicCourseDetail $details): View
    {
        $blocks = HomepageBlock::query()->where('visible', true)->orderBy('sort')->orderBy('id')->get();
        $hasHero = $blocks->contains('type', 'hero');
        $disciplines = Category::query()->where('status', 'active')->whereNull('parent_id');
        $featured = $blocks->contains('type', 'featured_courses')
            ? $catalog->published()->where('featured', true)->with(['category', 'instructors'])
                ->orderByRaw('case slug_en when ? then 0 when ? then 1 when ? then 2 else 3 end', [
                    'animation-lab', 'voice-acting-and-dubbing', 'drawing-basics-for-beginners',
                ])->latest()->orderByDesc('id')->limit(3)->get()
            : new Collection;
        $free = $blocks->contains('type', 'free_courses')
            ? $catalog->matching(['access' => 'free'], null)->reorder()
                ->orderByRaw('case slug_en when ? then 0 else 1 end', ['kids-art-colour-and-fun'])
                ->latest()->orderByDesc('id')->first()
            : null;
        $homepageCourses = $free === null ? $featured : $featured->merge([$free]);
        $profiles = $instructors->forUsers($featured->flatMap(static fn (Course $course): array => $course->instructors->modelKeys()));
        $market = Market::Egypt;

        return view('cms.home', [
            'seo' => PageSeo::home(),
            'blocks' => $blocks,
            'categories' => $blocks->contains('type', 'categories')
                ? (clone $disciplines)->orderBy('sort')->orderBy('id')->limit(8)->get()
                : collect(),
            'formatCounts' => $hasHero
                ? $catalog->published()->selectRaw('format, count(*) as total')->groupBy('format')->pluck('total', 'format')
                : collect(),
            'disciplineCount' => $hasHero ? $disciplines->count() : 0,
            'instructorCount' => $hasHero ? $instructors->query()->count() : 0,
            'learnerCount' => $hasHero ? User::query()->where('status', 'active')
                ->whereHas('roles', static fn ($roles) => $roles->where('name', 'student'))->count() : 0,
            'featured' => $featured,
            'free' => $free,
            'courseInstructorNames' => $profiles->map->publicName(),
            'courseSummaries' => $details->lessonSummaries($homepageCourses),
            'courseRatings' => $featured->mapWithKeys(static fn (Course $course): array => [$course->id => ReviewSummary::for($course)]),
            'featuredPrices' => $offers->coursePrices($featured, $market),
            'homeInstructors' => $blocks->contains('type', 'instructors')
                ? $instructors->query()->with(['user', 'disciplines' => static fn ($categories) => $categories->where('categories.status', 'active')])
                    ->orderByDesc('id')->limit(4)->get()
                : collect(),
            'posts' => $blocks->contains('type', 'blog')
                ? BlogPost::query()->live()->with(['category', 'author'])->latest('published_at')->orderByDesc('id')->limit(3)->get()
                : collect(),
            'market' => $market,
        ]);
    }

    public function show(string $slug): View
    {
        $page = CmsPage::query()->where('slug', $slug)->where('status', CmsPage::PUBLISHED)->firstOrFail();

        return view('cms.page', ['page' => $page, 'seo' => PageSeo::page($page)]);
    }

    public function preview(CmsPage $page): Response
    {
        return response()->view('cms.page', ['page' => $page, 'preview' => true])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
