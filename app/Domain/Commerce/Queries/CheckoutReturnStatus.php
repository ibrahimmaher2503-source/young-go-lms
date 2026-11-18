<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Models\User;

/**
 * Read-only state for the page the payer lands on after the gateway (A1). It takes no gateway parameters: whatever the
 * browser carries in the return URL is ignored, and the order is never written. Only the verified callback pays an order.
 */
final class CheckoutReturnStatus
{
    /** @return array{order_status: string, attempt_status: string|null, paid: bool}|null null when the order is not the payer's */
    public function __invoke(User $payer, Order $order): ?array
    {
        if ((int) $order->payer_id !== (int) $payer->getKey()) {
            return null;
        }

        $status = Order::query()->whereKey($order->getKey())->value('status');
        $current = $status instanceof OrderStatus ? $status : OrderStatus::from((string) $status);
        $attempt = PaymentAttempt::query()->where('order_id', $order->getKey())->orderByDesc('sequence')->first();

        return [
            'order_status' => $current->value,
            'attempt_status' => $attempt?->status->value,
            'paid' => $current === OrderStatus::Paid,
        ];
    }
}
