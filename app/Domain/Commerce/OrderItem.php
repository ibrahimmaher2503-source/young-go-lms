<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Domain\Shared\Money\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One product for one learner, with the price taken at order time. The snapshot (price, currency, titles) never
 * changes; the allocation column is the line's share of the order total, used later by refunds and the ledger.
 *
 * @property int $id
 * @property int $order_id
 * @property ProductType $product_type
 * @property int $product_id
 * @property int $learner_id
 * @property int $unit_price_minor
 * @property string $currency
 * @property int $discount_minor
 * @property int|null $allocated_minor
 * @property string|null $title_ar
 * @property string|null $title_en
 */
final class OrderItem extends Model
{
    private const array SNAPSHOT_COLUMNS = ['order_id', 'product_type', 'product_id', 'learner_id', 'unit_price_minor', 'currency', 'title_ar', 'title_en'];

    protected $table = 'order_items';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(static function (self $item): void {
            if ($item->isDirty(self::SNAPSHOT_COLUMNS)) {
                throw new LogicException('An order line price snapshot cannot change.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'unit_price_minor' => 'integer',
            'discount_minor' => 'integer',
            'allocated_minor' => 'integer',
        ];
    }

    public function unitPrice(): Money
    {
        return Money::ofMinor($this->unit_price_minor, $this->currency);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }
}
