<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A frozen monthly statement (T-P8-10): totals and the line snapshot are written once and never changed or deleted. A correction
 * lands as a ledger adjustment in a later period, so a statement already sent to the instructor never silently moves.
 *
 * @property int $id
 * @property string $number
 * @property int $instructor_id
 * @property string $currency
 * @property string $period YYYY-MM
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int $entry_count
 * @property int $earnings_minor
 * @property int $reversals_minor
 * @property int $adjustments_minor
 * @property int $net_minor
 * @property int $settled_minor
 * @property int $balance_forward_minor
 * @property int $closing_balance_minor
 * @property array<string, mixed> $snapshot
 * @property int|null $generated_by
 * @property Carbon $generated_at
 */
final class InstructorStatement extends Model
{
    protected $table = 'instructor_statements';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('A statement is frozen and cannot be changed.');
        });
        self::deleting(static function (): never {
            throw new LogicException('A statement cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'snapshot' => 'array', 'generated_at' => 'datetime',
            'entry_count' => 'integer', 'earnings_minor' => 'integer', 'reversals_minor' => 'integer', 'adjustments_minor' => 'integer',
            'net_minor' => 'integer', 'settled_minor' => 'integer', 'balance_forward_minor' => 'integer', 'closing_balance_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}
