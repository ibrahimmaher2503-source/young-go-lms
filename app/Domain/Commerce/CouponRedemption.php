<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One coupon use on one order (unique per order). The discount is the amount taken off at apply time, in minor units.
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $order_id
 * @property int $user_id
 * @property int $discount_minor
 * @property string $currency
 * @property CouponRedemptionStatus $status
 * @property Carbon $reserved_at
 * @property Carbon|null $redeemed_at
 * @property Carbon|null $released_at
 */
final class CouponRedemption extends Model
{
    protected $table = 'coupon_redemptions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
            'status' => CouponRedemptionStatus::class,
            'reserved_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
