<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\ProductType;
use App\Domain\Shared\Markets\Market;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Checkout entry (T-P8-12; products: course, bundle and subscription plan): returns the payer's still-open order for the same single product and learner, or creates one
 * through `CreateOrder`. Reloading or re-clicking therefore never piles up pending orders (each could hold a coupon use).
 * The idempotency key only makes a double submit of the same form safe; it never revives a finished order.
 */
final class StartCheckoutOrder
{
    public function __construct(
        private readonly CreateOrder $create,
        private readonly RenewOrderSeatHolds $renew,
        private readonly TransitionOrder $transition,
    ) {}

    public function __invoke(User $payer, Market $market, OrderLine $line, string $idempotencyKey): Order
    {
        return DB::transaction(function () use ($payer, $market, $line, $idempotencyKey): Order {
            User::query()->lockForUpdate()->findOrFail($payer->getKey());

            return $this->start($payer, $market, $line, $idempotencyKey);
        }, 3);
    }

    private function start(User $payer, Market $market, OrderLine $line, string $idempotencyKey): Order
    {
        $open = Order::query()
            ->where('payer_id', $payer->getKey())->where('status', OrderStatus::Pending->value)->where('market', $market->value)
            ->has('items', '=', 1)
            ->whereHas('items', static fn ($items) => $items->where('product_type', $line->type->value)
                ->where('product_id', $line->productId)->where('learner_id', $line->learner->getKey()))
            ->latest('id')->lockForUpdate()->first();

        if ($open !== null) {
            $changed = $line->type === ProductType::Bundle
                && ! hash_equals($open->request_hash, $this->create->fingerprint($market, [$line]));
            if (! $changed) {
                ($this->renew)($open);

                return $open;
            }
            if (Order::query()->where('payer_id', $payer->getKey())->where('idempotency_key', $idempotencyKey)->exists()) {
                throw ValidationException::withMessages(['order' => __('commerce.idempotency_conflict')]);
            }
            // An old payment attempt can still receive a successful callback.
            if (PaymentAttempt::query()->where('order_id', $open->id)->exists()
                || ManualPaymentProof::query()->where('order_id', $open->id)->exists()) {
                return $open;
            }

            ($this->transition)($open, OrderStatus::Cancelled, $payer);
        }

        return ($this->create)($payer, $market, [$line], $idempotencyKey);
    }
}
