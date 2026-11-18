<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\RefundStatus;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The payer's own payment history: every order the account paid or tried to pay, newest first, with its lines and refunds
 * (executed ones as money returned, open ones as "in review"). Scoped to the account holder (`payer_id`), never to a learner
 * context: a parent sees the orders they paid, a child never sees the parent's. Money columns come from the immutable order.
 */
final class PaymentHistory
{
    public const array RECEIPT_STATUSES = [OrderStatus::Paid, OrderStatus::PartiallyRefunded, OrderStatus::Refunded, OrderStatus::Disputed];

    /** @return LengthAwarePaginator<int, Order> */
    public static function for(User $payer, int $perPage = 10): LengthAwarePaginator
    {
        return Order::query()
            ->where('payer_id', $payer->getKey())
            ->with(['items', 'refunds' => static fn ($refunds) => $refunds->whereIn('status', [RefundStatus::Executed->value, RefundStatus::Requested->value, RefundStatus::Approved->value, RefundStatus::Processing->value])->orderBy('id')])
            ->withSum(['refunds as refunded_minor' => static fn ($refunds) => $refunds->where('status', RefundStatus::Executed->value)], 'amount_minor')
            ->latest('id')
            ->paginate($perPage);
    }

    public static function hasReceipt(Order $order): bool
    {
        return $order->paid_at !== null && in_array($order->status, self::RECEIPT_STATUSES, true);
    }
}
