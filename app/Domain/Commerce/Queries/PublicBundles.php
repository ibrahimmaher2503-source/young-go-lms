<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\Bundle;
use App\Domain\Shared\Markets\Market;
use Illuminate\Support\Collection;

/**
 * Public display hook for bundles: the one definition of a sellable bundle. It is active, the market is active, it has a
 * price for that market and every member course is published. No public page or route uses it yet (checkout UI is T-P8-12).
 */
final class PublicBundles
{
    /** @return Collection<int, Bundle> with prices and items.course loaded */
    public function forMarket(Market $market): Collection
    {
        if (! $market->isActive()) {
            return new Collection;
        }

        return Bundle::query()->where('is_active', true)->with(['prices', 'items.course'])->orderByDesc('id')->get()
            ->filter(static fn (Bundle $b): bool => $b->priceFor($market) !== null && $b->items->isNotEmpty()
                && $b->items->every(static fn ($i): bool => $i->course?->status === CourseStatus::Published))
            ->values();
    }
}
