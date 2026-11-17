<?php

declare(strict_types=1);

namespace App\Domain\Seo\Actions;

use App\Domain\Seo\PublicUrl;
use App\Domain\Seo\Queries\SitemapEntries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/** Renders sitemap.xml with hreflang alternates and caches it; refreshed on public content changes and daily. */
final class BuildSitemap
{
    /** The sitemap protocol allows at most 50,000 URLs per file. */
    public const MAX_URLS = 50000;

    public function __construct(private readonly SitemapEntries $entries) {}

    public function cached(): string
    {
        return Cache::remember(Config::string('seo.sitemap_key'), Config::integer('seo.sitemap_ttl'), $this->render(...));
    }

    /** Drop the cached copy and render again. */
    public function refresh(): string
    {
        Cache::forget(Config::string('seo.sitemap_key'));

        return $this->cached();
    }

    public function forget(): void
    {
        Cache::forget(Config::string('seo.sitemap_key'));
    }

    public function render(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        $count = 0;

        foreach ($this->entries->all() as $entry) {
            $alternates = PublicUrl::alternates($entry['urls']);
            foreach ($entry['urls'] as $url) {
                if (++$count > self::MAX_URLS) {
                    break 2;
                }
                $xml .= '<url><loc>'.self::escape($url).'</loc>';
                if ($entry['lastmod'] !== null) {
                    $xml .= '<lastmod>'.self::escape($entry['lastmod']).'</lastmod>';
                }
                foreach ($alternates as $hreflang => $href) {
                    $xml .= '<xhtml:link rel="alternate" hreflang="'.self::escape($hreflang).'" href="'.self::escape($href).'"/>';
                }
                $xml .= "</url>\n";
            }
        }

        return $xml.'</urlset>'."\n";
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
