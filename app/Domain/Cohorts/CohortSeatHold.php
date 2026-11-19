<?php

declare(strict_types=1);

namespace App\Domain\Cohorts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A seat held for an unpaid order line. It counts against the cohort capacity while `held` and not yet expired.
 *
 * @property int $id
 * @property int $cohort_id
 * @property int $learner_id
 * @property int $order_item_id
 * @property string $status
 * @property Carbon $expires_at
 * @property string|null $release_reason
 */
final class CohortSeatHold extends Model
{
    public const string HELD = 'held';

    public const string CONVERTED = 'converted';

    public const string RELEASED = 'released';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'converted_at' => 'datetime', 'released_at' => 'datetime'];
    }

    /** Minutes a hold lasts (config/commerce.php). */
    public static function holdMinutes(): int
    {
        return max(1, (int) config('commerce.seat_hold_minutes', 30));
    }

    /**
     * @param  Builder<CohortSeatHold>  $query
     * @return Builder<CohortSeatHold>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::HELD)->where('expires_at', '>', now());
    }

    /** @return BelongsTo<Cohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }
}
