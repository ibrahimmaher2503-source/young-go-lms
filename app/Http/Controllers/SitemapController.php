<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Seo\Actions\BuildSitemap;
use Illuminate\Http\Response;

final class SitemapController
{
    public function __invoke(BuildSitemap $sitemap): Response
    {
        return response($sitemap->cached(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
