<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\RefundStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * The payer's own payment history: every order the account paid or tried to pay, newest first, with its lines and refunds
 * (executed ones as money returned, open ones as "in review"). Scoped to the account holder (`payer_id`), never to a learner
 * context: a parent sees the orders they paid, a child never sees the parent's. Money columns come from the immutable order.
 */
final class PaymentHistory
{
    public const array RECEIPT_STATUSES = [OrderStatus::Paid, OrderStatus::PartiallyRefunded, OrderStatus::Refunded, OrderStatus::Disputed];

    /** @return LengthAwarePaginator<int, Order> */
    public static function for(User $payer, int $perPage = 10, ?OrderStatus $status = null): LengthAwarePaginator
    {
        return Order::query()
            ->where('payer_id', $payer->getKey())
            ->when($status !== null, static fn ($orders) => $orders->where('status', $status->value))
            ->with(['items', 'refunds' => static fn ($refunds) => $refunds->whereIn('status', [RefundStatus::Executed->value, RefundStatus::Requested->value, RefundStatus::Approved->value, RefundStatus::Processing->value])->orderBy('id')])
            ->withSum(['refunds as refunded_minor' => static fn ($refunds) => $refunds->where('status', RefundStatus::Executed->value)], 'amount_minor')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Header figures for the payments page: what the account paid (per currency), the last payment and how many orders sit in each status.
     *
     * @return array{orders: int, paid: array<string, int>, last_paid_at: CarbonInterface|null, counts: array<string, int>}
     */
    public static function summary(User $payer): array
    {
        $orders = Order::query()->where('payer_id', $payer->getKey());
        $counts = (clone $orders)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(static fn (mixed $n): int => (int) $n)->all();
        $paid = (clone $orders)->whereNotNull('paid_at')->whereIn('status', array_map(static fn (OrderStatus $s): string => $s->value, self::RECEIPT_STATUSES))
            ->selectRaw('currency, sum(total_minor) as total')->groupBy('currency')->pluck('total', 'currency')->map(static fn (mixed $n): int => (int) $n)->all();
        $last = (clone $orders)->whereNotNull('paid_at')->max('paid_at');

        return ['orders' => array_sum($counts), 'paid' => $paid, 'last_paid_at' => $last === null ? null : Carbon::parse((string) $last), 'counts' => $counts];
    }

    public static function hasReceipt(Order $order): bool
    {
        return $order->paid_at !== null && in_array($order->status, self::RECEIPT_STATUSES, true);
    }
}
