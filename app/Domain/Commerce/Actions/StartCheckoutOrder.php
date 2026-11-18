<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Shared\Markets\Market;
use App\Models\User;

/**
 * Checkout entry (T-P8-12): returns the payer's still-open order for the same single product and learner, or creates one
 * through `CreateOrder`. Reloading or re-clicking therefore never piles up pending orders (each could hold a coupon use).
 * The idempotency key only makes a double submit of the same form safe; it never revives a finished order.
 */
final class StartCheckoutOrder
{
    public function __construct(private readonly CreateOrder $create) {}

    public function __invoke(User $payer, Market $market, OrderLine $line, string $idempotencyKey): Order
    {
        $open = Order::query()
            ->where('payer_id', $payer->getKey())->where('status', OrderStatus::Pending->value)->where('market', $market->value)
            ->has('items', '=', 1)
            ->whereHas('items', static fn ($items) => $items->where('product_type', $line->type->value)
                ->where('product_id', $line->productId)->where('learner_id', $line->learner->getKey()))
            ->latest('id')->first();

        return $open ?? ($this->create)($payer, $market, [$line], $idempotencyKey);
    }
}
