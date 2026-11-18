<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\Bundle;
use App\Domain\Shared\Markets\Market;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Public display hook for bundles: the one definition of a sellable bundle. It is active, the market is active, it has a
 * price for that market and every member course is published. Used by the public bundle page and the checkout (T-P8-12).
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

    /** URL key of a bundle page: `<id>-<slug of the name>` (the id alone when the name has no Latin letters). Bundles have no slug column. */
    public static function keyFor(Bundle $bundle, string $locale): string
    {
        $slug = Str::slug(Str::limit((string) $bundle->getTranslation('name', $locale), 60, ''));

        return $slug === '' ? (string) $bundle->getKey() : $bundle->getKey().'-'.$slug;
    }

    /** The bundle id a URL key points at (the part before the first dash), or null. */
    public static function idFromKey(string $key): ?int
    {
        return preg_match('/^(\d{1,9})(?:-.*)?$/', $key, $m) === 1 ? (int) $m[1] : null;
    }
}
