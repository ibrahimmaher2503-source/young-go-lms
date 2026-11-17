<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A percent or fixed discount with a date window, a total cap, a per-payer cap and an optional course restriction
 * (PRD 17.3). A percent is a whole number 1 to 100; a fixed amount is integer minor units in one currency. With no
 * course rows the coupon applies to every course. `redemptions_count` is owned by the coupon Actions.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property CouponType $discount_type
 * @property int|null $percent
 * @property int|null $amount_minor
 * @property string|null $currency
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $max_redemptions
 * @property int|null $max_per_user
 * @property int $redemptions_count
 * @property bool $is_active
 * @property int|null $created_by
 */
final class Coupon extends Model
{
    protected $table = 'coupons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'discount_type' => CouponType::class,
            'percent' => 'integer',
            'amount_minor' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_redemptions' => 'integer',
            'max_per_user' => 'integer',
            'redemptions_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Course, $this> */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'coupon_courses');
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
