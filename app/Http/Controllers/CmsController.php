<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Models\HomepageBlock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

final class CmsController
{
    public function home(): View
    {
        return view('cms.home', [
            'blocks' => HomepageBlock::query()->where('visible', true)->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function show(string $slug): View
    {
        return view('cms.page', [
            'page' => CmsPage::query()->where('slug', $slug)->where('status', CmsPage::PUBLISHED)->firstOrFail(),
        ]);
    }

    public function preview(CmsPage $page): Response
    {
        return response()->view('cms.page', ['page' => $page, 'preview' => true])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
