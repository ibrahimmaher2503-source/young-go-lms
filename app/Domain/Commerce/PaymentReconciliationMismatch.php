<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One difference found by reconciliation (T-P8-11). Evidence for finance; it never drives an order state change.
 *
 * @property int $id
 * @property int $payment_reconciliation_id
 * @property string $gateway
 * @property ReconciliationMismatchType $type
 * @property string $mismatch_key
 * @property string|null $provider_transaction_id
 * @property string|null $attempt_reference
 * @property int|null $payment_attempt_id
 * @property int|null $order_id
 * @property int|null $gateway_amount_minor
 * @property int|null $local_amount_minor
 * @property string|null $currency
 * @property string $reason
 * @property ReconciliationMismatchStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon $created_at
 */
final class PaymentReconciliationMismatch extends Model
{
    protected $table = 'payment_reconciliation_mismatches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ReconciliationMismatchType::class,
            'status' => ReconciliationMismatchStatus::class,
            'gateway_amount_minor' => 'integer',
            'local_amount_minor' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PaymentReconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(PaymentReconciliation::class, 'payment_reconciliation_id');
    }

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
