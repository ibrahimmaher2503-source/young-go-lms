<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions;

use App\Domain\Commerce\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One paid span of a subscription, created from one paid order line. Access ends at `grace_ends_at`. The entitlements it
 * granted carry the source reference from entitlementRef() (also the once-per-student-course-period key for T-P8-09).
 *
 * @property int $id
 * @property int $subscription_id
 * @property int $sequence
 * @property int $order_item_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon $grace_ends_at
 */
final class SubscriptionPeriod extends Model
{
    protected $table = 'subscription_periods';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'grace_ends_at' => 'datetime'];
    }

    public function entitlementRef(): string
    {
        return 'subscription_period:'.$this->getKey();
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
