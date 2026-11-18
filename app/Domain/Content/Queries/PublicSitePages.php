<?php

declare(strict_types=1);

namespace App\Domain\Content\Queries;

use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Collection;

final class PublicSitePages
{
    /** @return Collection<string, CmsPage> */
    public function __invoke(): Collection
    {
        return CmsPage::query()
            ->where('status', CmsPage::PUBLISHED)
            ->whereIn('seed_key', ['about', 'faq', 'privacy', 'terms', 'refund'])
            ->get(['id', 'seed_key', 'slug', 'title'])
            ->keyBy(static fn (CmsPage $page): string => (string) $page->getAttribute('seed_key'));
    }
}
