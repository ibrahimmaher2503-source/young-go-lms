<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The price of a bundle in one market, in integer minor units. One row per bundle and market.
 *
 * @property int $id
 * @property int $bundle_id
 * @property string $market
 * @property string $currency
 * @property int $amount_minor
 */
final class BundlePrice extends Model
{
    public $timestamps = false;

    protected $table = 'bundle_prices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function money(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }

    public function forMarket(Market $market): bool
    {
        return $this->market === $market->value && $this->currency === $market->currency();
    }

    /** @return BelongsTo<Bundle, $this> */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }
}
