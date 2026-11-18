<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Queries\CatalogQuery;
use App\Domain\Seo\PageSeo;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\HomepageBlock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

final class CmsController
{
    public function home(CatalogQuery $catalog): View
    {
        $blocks = HomepageBlock::query()->where('visible', true)->orderBy('sort')->orderBy('id')->get();

        return view('cms.home', [
            'seo' => PageSeo::home(),
            'blocks' => $blocks,
            'categories' => $blocks->contains('type', 'categories')
                ? Category::query()->where('status', 'active')->whereNull('parent_id')->orderBy('sort')->orderBy('id')->limit(4)->get()
                : collect(),
            'featured' => $blocks->contains('type', 'featured_courses')
                ? $catalog->published()->where('featured', true)->with(['category', 'instructors'])->latest()->orderByDesc('id')->limit(3)->get()
                : collect(),
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
