<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One reconciliation run: a gateway and a day (T-P8-11). Re-running the same day updates the counts.
 *
 * @property int $id
 * @property string $gateway
 * @property Carbon $run_date
 * @property Carbon $window_start
 * @property Carbon $window_end
 * @property int $transactions_seen
 * @property int $local_attempts
 * @property int $matched
 * @property int $mismatched
 * @property Carbon $completed_at
 */
final class PaymentReconciliation extends Model
{
    protected $table = 'payment_reconciliations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'run_date' => 'date:Y-m-d',
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'completed_at' => 'datetime',
            'transactions_seen' => 'integer',
            'local_attempts' => 'integer',
            'matched' => 'integer',
            'mismatched' => 'integer',
        ];
    }

    /** @return HasMany<PaymentReconciliationMismatch, $this> */
    public function mismatches(): HasMany
    {
        return $this->hasMany(PaymentReconciliationMismatch::class);
    }
}
