<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One commission rule: a percentage (`rate_bps`, 2500 = 25%) or a fixed amount (`fixed_amount_minor` + `currency`) for a sale
 * type, scoped to an instructor and/or a course, valid from `effective_from` to `effective_to` (both inclusive, null = open).
 * Rate, scope and start never change once saved (ledger entries point at the rule): a new rate is a new row, and the old one is
 * closed by setting `effective_to`.
 *
 * @property int $id
 * @property int|null $instructor_id
 * @property int|null $course_id
 * @property SaleType $sale_type
 * @property int|null $rate_bps
 * @property int|null $fixed_amount_minor
 * @property string|null $currency
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $note
 * @property int|null $created_by
 */
final class CommissionRule extends Model
{
    private const array FROZEN_COLUMNS = ['instructor_id', 'course_id', 'sale_type', 'rate_bps', 'fixed_amount_minor', 'currency', 'effective_from'];

    protected $table = 'course_commission_rules';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(static function (self $rule): void {
            if ($rule->isDirty(self::FROZEN_COLUMNS)) {
                throw new LogicException('A commission rule cannot be changed; close it and add a new one.');
            }
        });
        self::deleting(static function (): never {
            throw new LogicException('A commission rule cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'sale_type' => SaleType::class, 'rate_bps' => 'integer', 'fixed_amount_minor' => 'integer',
            'effective_from' => 'date', 'effective_to' => 'date',
        ];
    }

    public function isFixed(): bool
    {
        return $this->fixed_amount_minor !== null;
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
}
