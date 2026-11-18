<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

use App\Domain\Commerce\Order;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One line of the append-only instructor ledger (PRD 17.4). `amount_minor` is signed: earnings are positive, reversals negative,
 * adjustments either. History is never edited or deleted; a mistake is corrected by a reversal or adjustment line. Only `status`
 * may change (settlement, T-P8-10). `source_key` is unique, so one source event can never write two lines.
 *
 * @property int $id
 * @property int $instructor_id
 * @property int|null $course_id
 * @property LedgerEntryType $type
 * @property SaleType|null $sale_type
 * @property LedgerStatus $status
 * @property string $source_key
 * @property int|null $order_id
 * @property int|null $order_item_id
 * @property int|null $allocation_id
 * @property int|null $subscription_period_id
 * @property int|null $refund_id
 * @property int|null $reverses_entry_id
 * @property int|null $student_id
 * @property int|null $rule_id
 * @property string|null $rule_source
 * @property string $currency
 * @property int|null $gross_minor
 * @property int|null $discount_minor
 * @property int|null $base_minor
 * @property int|null $rate_bps
 * @property int|null $fixed_amount_minor
 * @property int $amount_minor
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon $earned_at
 */
final class LedgerEntry extends Model
{
    protected $table = 'instructor_ledger_entries';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(static function (self $entry): void {
            if (array_diff(array_keys($entry->getDirty()), ['status', 'updated_at']) !== []) {
                throw new LogicException('A ledger entry cannot be changed; add a reversal or adjustment.');
            }
        });
        self::deleting(static function (): never {
            throw new LogicException('A ledger entry cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class, 'sale_type' => SaleType::class, 'status' => LedgerStatus::class,
            'gross_minor' => 'integer', 'discount_minor' => 'integer', 'base_minor' => 'integer', 'rate_bps' => 'integer',
            'fixed_amount_minor' => 'integer', 'amount_minor' => 'integer', 'earned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
