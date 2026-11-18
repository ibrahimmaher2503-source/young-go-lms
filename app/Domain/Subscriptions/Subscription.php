<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A learner subscription to one plan: one row per learner and plan, extended by one period for every paid renewal.
 * `payer` is who pays (a parent for a child). Cancelling keeps access to the end of the paid period.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $learner_id
 * @property int $payer_id
 * @property SubscriptionStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $expired_at
 */
final class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class, 'started_at' => 'datetime', 'cancelled_at' => 'datetime', 'expired_at' => 'datetime'];
    }

    public function latestPeriod(): ?SubscriptionPeriod
    {
        $periods = $this->relationLoaded('periods') ? $this->periods : $this->periods()->get();

        return $periods->sortByDesc('sequence')->first();
    }

    /** Human state for lists: active, grace (period over, access still open), cancelled, expired. */
    public function displayState(): string
    {
        $latest = $this->latestPeriod();

        return match (true) {
            $this->status === SubscriptionStatus::Expired => 'expired',
            $this->cancelled_at !== null => 'cancelled',
            $latest !== null && $latest->ends_at->lte(now()) => 'grace',
            default => 'active',
        };
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /** @return HasMany<SubscriptionPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(SubscriptionPeriod::class)->orderBy('sequence');
    }
}
