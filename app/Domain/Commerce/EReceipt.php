<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The receipt of one sale or one refund: its number, amounts and an ETA-ready snapshot (seller, buyer, lines, totals, tax)
 * frozen at issue. `status` stays `not_submitted` until an e-receipt provider is enabled (O-023).
 *
 * @property int $id
 * @property string $receipt_key
 * @property string $number
 * @property ReceiptKind $kind
 * @property int $order_id
 * @property int|null $refund_id
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int|null $tax_minor
 * @property int $total_minor
 * @property EReceiptStatus $status
 * @property string|null $provider
 * @property string|null $provider_uuid
 * @property Carbon|null $submitted_at
 * @property string|null $failure_code
 * @property array<string, mixed> $snapshot
 * @property Carbon $issued_at
 */
final class EReceipt extends Model
{
    protected $table = 'e_receipts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => ReceiptKind::class,
            'status' => EReceiptStatus::class,
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'snapshot' => 'array',
            'submitted_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Refund, $this> */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
