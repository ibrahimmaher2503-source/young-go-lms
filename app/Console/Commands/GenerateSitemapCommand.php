<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Seo\Actions\BuildSitemap;
use Illuminate\Console\Command;

/** Daily rebuild of the cached sitemap (also rebuilt lazily after any public content change). */
final class GenerateSitemapCommand extends Command
{
    protected $signature = 'seo:sitemap';

    protected $description = 'Rebuild the cached sitemap.xml.';

    public function handle(BuildSitemap $sitemap): int
    {
        $xml = $sitemap->refresh();
        $this->info('Sitemap rebuilt: '.substr_count($xml, '<url>').' URLs.');

        return self::SUCCESS;
    }
}
