<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A settlement batch (T-P8-10): the record that finance reviewed and paid a set of ledger entries, outside the system. Never
 * deleted; changed only by the settlement Actions (status, approval, payout and cancellation fields).
 *
 * @property int $id
 * @property string $reference
 * @property string|null $request_key
 * @property string $currency
 * @property SettlementStatus $status
 * @property string|null $note
 * @property int $instructor_count
 * @property int $entry_count
 * @property int $total_minor
 * @property list<array<string, mixed>>|null $carried_forward
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 */
final class Settlement extends Model
{
    protected $table = 'instructor_settlements';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(static function (self $settlement): void {
            $locked = ['reference', 'request_key', 'currency', 'note', 'instructor_count', 'entry_count', 'total_minor', 'carried_forward', 'created_by'];
            if (array_intersect(array_keys($settlement->getDirty()), $locked) !== []) {
                throw new LogicException('The contents of a settlement cannot be changed.');
            }
        });
        self::deleting(static function (): never {
            throw new LogicException('A settlement cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class, 'carried_forward' => 'array', 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
            'instructor_count' => 'integer', 'entry_count' => 'integer', 'total_minor' => 'integer',
        ];
    }

    /** @return HasMany<SettlementItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class, 'settlement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
