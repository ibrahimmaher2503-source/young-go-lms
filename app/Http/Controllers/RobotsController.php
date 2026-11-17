<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

/** robots.txt: private areas are disallowed; outside production (or with SEO_INDEXING off) everything is. */
final class RobotsController
{
    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (Config::boolean('seo.indexing')) {
            foreach (Config::array('seo.disallow') as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = 'Allow: /';
            $lines[] = '';
            $lines[] = 'Sitemap: '.url('/sitemap.xml');
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
