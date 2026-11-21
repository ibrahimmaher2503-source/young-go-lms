<?php

declare(strict_types=1);

namespace App\Domain\Content\Queries;

use App\Domain\Instructors\Queries\PublicInstructors;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\HomepageBlock;
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

    public function hasHomeDisciplines(): bool
    {
        return HomepageBlock::query()->where('type', 'categories')->where('visible', true)->exists()
            && Category::query()->where('status', 'active')->whereNull('parent_id')->exists();
    }

    public function hasHomeInstructors(PublicInstructors $instructors): bool
    {
        $block = HomepageBlock::query()->where('type', 'instructors')->where('visible', true)
            ->orderBy('sort')->orderBy('id')->first(['body']);

        return $block !== null && (filled($block->body) || $instructors->query()->exists());
    }
}
