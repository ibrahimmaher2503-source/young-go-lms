<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staff read model of orders: each row carries the gateway of its latest payment attempt and whether a manual transfer
 * proof exists. Read only; it never writes an order, payment or entitlement.
 */
final class OrderDirectory
{
    /** @return Builder<Order> */
    public static function query(): Builder
    {
        return Order::query()->addSelect([
            'orders.*',
            'last_gateway' => PaymentAttempt::query()->select('gateway')->whereColumn('payment_attempts.order_id', 'orders.id')->orderByDesc('sequence')->limit(1),
            'has_manual_proof' => ManualPaymentProof::query()->selectRaw('1')->whereColumn('manual_payment_proofs.order_id', 'orders.id')->limit(1),
        ])->with('payer');
    }

    /** @return array{pending: int, paid: int} */
    public static function summary(): array
    {
        return [
            'pending' => Order::query()->where('status', OrderStatus::Pending->value)->count(),
            'paid' => Order::query()->where('status', OrderStatus::Paid->value)->count(),
        ];
    }
}
