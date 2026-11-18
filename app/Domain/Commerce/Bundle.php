<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Domain\Shared\Markets\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A priced set of courses sold as one product (PRD 17.1, ED-012). The price lives in `bundle_prices`, one row per market,
 * in integer minor units. `bundle_items` lists the member courses; an optional weight per course drives how the price is
 * allocated for the ledger (AllocateBundlePrice, O-009). Only an active bundle can be ordered.
 *
 * @property int $id
 * @property bool $is_active
 * @property int|null $created_by
 */
final class Bundle extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'bundles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function priceFor(Market $market): ?BundlePrice
    {
        $price = $this->prices->first(static fn (BundlePrice $p): bool => $p->market === $market->value);

        return $price instanceof BundlePrice && $price->forMarket($market) ? $price : null;
    }

    /** @return HasMany<BundleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<BundlePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(BundlePrice::class);
    }
}
