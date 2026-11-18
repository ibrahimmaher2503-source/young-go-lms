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
 * One instructor's line inside a settlement: the amount to pay and, once paid, the offline payout method, reference and date.
 * Amount and links are fixed at creation; only the payout fields are written, once.
 *
 * @property int $id
 * @property int $settlement_id
 * @property int $instructor_id
 * @property int $entry_count
 * @property int $amount_minor
 * @property string|null $payout_method
 * @property string|null $payout_reference
 * @property Carbon|null $paid_on
 * @property Carbon|null $paid_at
 * @property int|null $paid_by
 */
final class SettlementItem extends Model
{
    protected $table = 'instructor_settlement_items';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(static function (self $item): void {
            if (array_diff(array_keys($item->getDirty()), ['payout_method', 'payout_reference', 'paid_on', 'paid_at', 'paid_by', 'updated_at']) !== [] || $item->getOriginal('paid_at') !== null) {
                throw new LogicException('A settlement line can only be marked paid once.');
            }
        });
        self::deleting(static function (): never {
            throw new LogicException('A settlement line cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'paid_at' => 'datetime', 'amount_minor' => 'integer', 'entry_count' => 'integer'];
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /** @return BelongsTo<Settlement, $this> */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class, 'settlement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** @return HasMany<SettlementEntry, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(SettlementEntry::class, 'item_id');
    }
}
