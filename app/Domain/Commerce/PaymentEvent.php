<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A signature-verified gateway callback, stored once (T-P8-03). `payload` is sanitised provider data only.
 *
 * @property int $id
 * @property string $gateway
 * @property string $event_key
 * @property string $provider_event_id
 * @property string $state
 * @property int|null $payment_attempt_id
 * @property int|null $order_id
 * @property string|null $attempt_reference
 * @property int $amount_minor
 * @property string $currency
 * @property PaymentEventOutcome $outcome
 * @property string|null $reason
 * @property array<string, mixed>|null $payload
 * @property Carbon $processed_at
 */
final class PaymentEvent extends Model
{
    protected $table = 'payment_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'outcome' => PaymentEventOutcome::class,
            'amount_minor' => 'integer',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
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
}
