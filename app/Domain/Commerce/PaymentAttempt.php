<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One try to collect an order's total through a gateway. The amount and currency are copied from the server order at
 * creation. `active_order_id` is set only while the attempt is open, so an order has at most one open attempt. The checkout
 * URL (carries the per-session client secret) is encrypted and hidden; no merchant secret is ever stored here.
 *
 * @property int $id
 * @property int $order_id
 * @property string $gateway
 * @property int $sequence
 * @property string $reference
 * @property PaymentAttemptStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $provider_intention_id
 * @property string|null $provider_order_id
 * @property string|null $checkout_url
 * @property string|null $failure_code
 * @property bool|null $signature_valid
 * @property array<string, mixed>|null $payload
 * @property Carbon $expires_at
 */
final class PaymentAttempt extends Model
{
    protected $table = 'payment_attempts';

    protected $guarded = ['id'];

    protected $hidden = ['checkout_url'];

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'amount_minor' => 'integer',
            'sequence' => 'integer',
            'checkout_url' => 'encrypted',
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
