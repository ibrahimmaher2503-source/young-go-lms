<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A server-priced order. The payer pays; each line names the learner who gets access (YG-GAP-FAM-005). Amounts are
 * integer minor units in the market currency. Totals never change once the order is paid, except through
 * `AdjustOrderTotal`, which unlocks them for one audited write.
 *
 * @property int $id
 * @property string $reference
 * @property int $payer_id
 * @property string $market
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $total_minor
 * @property OrderStatus $status
 * @property string $idempotency_key
 * @property string $request_hash
 * @property Carbon|null $paid_at
 */
final class Order extends Model
{
    /** Columns that fix what the customer owes. */
    private const array MONEY_COLUMNS = ['payer_id', 'market', 'currency', 'subtotal_minor', 'discount_minor', 'total_minor'];

    protected $table = 'orders';

    protected $guarded = ['id'];

    /** The idempotency fingerprint never leaves through serialization. */
    protected $hidden = ['request_hash'];

    private bool $totalsUnlocked = false;

    protected static function booted(): void
    {
        self::updating(static function (self $order): void {
            // getOriginal applies the enum cast; casting the enum to string was BUG-007 and broke every status change.
            $raw = $order->getOriginal('status');
            $original = $raw instanceof OrderStatus ? $raw : OrderStatus::tryFrom((string) $raw);
            if (! $order->totalsUnlocked && $original?->locksTotals() === true && $order->isDirty(self::MONEY_COLUMNS)) {
                throw new LogicException('Order totals are frozen once the order is paid.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** Runs the closure with the totals guard open. Only `AdjustOrderTotal` calls this. */
    public function withTotalsUnlocked(Closure $write): void
    {
        $this->totalsUnlocked = true;
        try {
            $write($this);
        } finally {
            $this->totalsUnlocked = false;
        }
    }

    public function total(): Money
    {
        return Money::ofMinor($this->total_minor, $this->currency);
    }

    public function isInActiveMarket(): bool
    {
        $market = Market::tryFrom($this->market);

        return $market !== null && $market->isActive() && $market->currency() === $this->currency;
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
