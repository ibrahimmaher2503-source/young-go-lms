<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A transfer proof a payer submitted for a pending order (YG-GAP-PAY-004). The file is private; lists show the payer
 * name and the sender account masked. `reference_key` and `active_order_id` are owned by the proof Actions.
 *
 * @property int $id
 * @property int $order_id
 * @property int $payer_id
 * @property string $payer_name
 * @property string $sender_account
 * @property string $transfer_reference
 * @property string|null $reference_key
 * @property int|null $active_order_id
 * @property int $amount_minor
 * @property string $currency
 * @property Carbon $transferred_at
 * @property string $file_path
 * @property string $file_mime
 * @property int $file_size
 * @property string $file_sha256
 * @property ManualPaymentStatus $status
 * @property Carbon $sla_due_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 */
final class ManualPaymentProof extends Model
{
    protected $table = 'manual_payment_proofs';

    protected $guarded = ['id'];

    /** The stored path and checksum never leave through serialization. */
    protected $hidden = ['file_path', 'file_sha256'];

    protected function casts(): array
    {
        return [
            'status' => ManualPaymentStatus::class,
            'amount_minor' => 'integer',
            'file_size' => 'integer',
            'transferred_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === ManualPaymentStatus::Pending;
    }

    /** Waiting for review past the SLA time. */
    public function isOverdue(): bool
    {
        return $this->isPending() && $this->sla_due_at->lte(now());
    }

    /** True when the amount sent is not exactly what the order total says (shown highlighted to finance). */
    public function amountMismatch(): bool
    {
        $order = $this->order;

        return $order === null || $order->total_minor !== $this->amount_minor || $order->currency !== $this->currency;
    }

    /** Sender account for lists: only the last three characters remain. */
    public function maskedSender(): string
    {
        return self::mask($this->sender_account, 3);
    }

    /** Payer name for lists: only the first character of each word remains. */
    public function maskedPayerName(): string
    {
        $words = preg_split('/\s+/u', trim($this->payer_name)) ?: [];

        return implode(' ', array_map(static fn (string $word): string => mb_substr($word, 0, 1).str_repeat('•', max(0, min(mb_strlen($word) - 1, 6))), $words));
    }

    private static function mask(string $value, int $keep): string
    {
        $length = mb_strlen($value);

        return $length <= $keep ? str_repeat('•', $length) : str_repeat('•', min($length - $keep, 8)).mb_substr($value, -$keep);
    }
}
