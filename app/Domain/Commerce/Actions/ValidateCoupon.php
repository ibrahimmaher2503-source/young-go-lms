<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\CouponQuote;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\CouponType;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Checks a coupon code against a pending order and prices the discount, entirely on the server (PRD 17.3). Refuses unknown
 * or inactive codes, codes outside their date window, used-up codes, codes the payer has already used up, fixed codes in
 * another currency, and orders with no eligible course. Writes nothing. `$lock` takes a row lock on the coupon; `ApplyCoupon`
 * passes it so the checks and the reservation see the same counters.
 */
final class ValidateCoupon
{
    public function __invoke(string $code, User $payer, Order $order, bool $lock = false): CouponQuote
    {
        if ($payer->getAttribute('status') !== 'active' || $order->payer_id !== $payer->getKey() || $order->status !== OrderStatus::Pending) {
            $this->refuse('coupon_order_invalid');
        }

        $normalized = Coupon::normalizeCode($code);
        $query = Coupon::query()->where('code', $normalized);
        if ($lock) {
            $query->lockForUpdate();
        }
        $coupon = preg_match('/^[A-Z0-9_-]{3,32}$/', $normalized) === 1 ? $query->first() : null;
        if (! $coupon instanceof Coupon || ! $coupon->is_active) {
            $this->refuse('coupon_invalid');
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) {
            $this->refuse('coupon_not_started');
        }
        if ($coupon->ends_at !== null && ! $coupon->ends_at->isFuture()) {
            $this->refuse('coupon_expired');
        }
        if ($coupon->max_redemptions !== null && $coupon->redemptions_count >= $coupon->max_redemptions) {
            $this->refuse('coupon_exhausted');
        }
        if ($coupon->max_per_user !== null && $this->usedBy($coupon, $payer) >= $coupon->max_per_user) {
            $this->refuse('coupon_user_limit');
        }
        if (CouponRedemption::query()->where('order_id', $order->getKey())->exists()) {
            $this->refuse('coupon_already_applied');
        }
        if ($coupon->discount_type === CouponType::Fixed && $coupon->currency !== $order->currency) {
            $this->refuse('coupon_ineligible');
        }

        $items = OrderItem::query()->where('order_id', $order->getKey())->orderBy('id')->get();
        $restricted = $coupon->courses()->pluck('courses.id')->map(static fn ($id): int => (int) $id)->all();
        $eligible = $items->filter(static fn (OrderItem $i): bool => $i->product_type === ProductType::Course
            && ($restricted === [] || in_array($i->product_id, $restricted, true)))->values();
        $eligibleMinor = (int) $eligible->sum('unit_price_minor');

        $discount = match ($coupon->discount_type) {
            CouponType::Percent => intdiv($eligibleMinor * (int) $coupon->percent, 100),
            CouponType::Fixed => min((int) $coupon->amount_minor, $eligibleMinor),
        };
        if ($discount <= 0) {
            $this->refuse('coupon_ineligible');
        }

        return new CouponQuote($coupon, $discount, $eligibleMinor, $this->allocate($eligible->all(), $discount, $eligibleMinor));
    }

    private function usedBy(Coupon $coupon, User $payer): int
    {
        return CouponRedemption::query()->where('coupon_id', $coupon->getKey())->where('user_id', $payer->getKey())
            ->whereIn('status', [CouponRedemptionStatus::Reserved->value, CouponRedemptionStatus::Redeemed->value])->count();
    }

    /**
     * Splits the discount across eligible lines by price, rounding down; leftover units go to the earliest lines that can take them.
     *
     * @param  list<OrderItem>  $items
     * @return array<int, int>
     */
    private function allocate(array $items, int $discount, int $eligibleMinor): array
    {
        $shares = [];
        $given = 0;
        foreach ($items as $item) {
            $shares[$item->getKey()] = intdiv($discount * $item->unit_price_minor, $eligibleMinor);
            $given += $shares[$item->getKey()];
        }
        $left = $discount - $given;
        foreach ($items as $item) {
            if ($left <= 0) {
                break;
            }
            $room = $item->unit_price_minor - $shares[$item->getKey()];
            $add = min($room, $left);
            $shares[$item->getKey()] += $add;
            $left -= $add;
        }

        return $shares;
    }

    private function refuse(string $reason): never
    {
        throw ValidationException::withMessages(['coupon' => __('commerce.'.$reason)]);
    }
}
