<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Domain\Shared\Money\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A refund of part or all of one paid order. The order, its payment attempts and events are never changed by a refund;
 * this row is the reversal record. `method` is fixed at request time from how the order was paid.
 *
 * @property int $id
 * @property string $reference
 * @property int $order_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $reason
 * @property RefundMethod $method
 * @property RefundStatus $status
 * @property string|null $gateway
 * @property string|null $provider_transaction_id
 * @property string|null $provider_refund_id
 * @property string|null $external_reference
 * @property string|null $failure_code
 * @property int $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $review_note
 * @property int|null $executed_by
 * @property Carbon|null $executed_at
 */
final class Refund extends Model
{
    protected $table = 'refunds';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'method' => RefundMethod::class,
            'status' => RefundStatus::class,
            'amount_minor' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function amount(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
