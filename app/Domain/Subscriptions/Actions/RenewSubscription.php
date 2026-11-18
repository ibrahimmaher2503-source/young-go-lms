<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\ProductType;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Manual renewal (R-017, no auto-charge): creates the pending order for the next period of a subscription. The payer is
 * the signed-in account (the subscriber or a parent of the child, checked by CreateOrder) and pays it like any order
 * (Paymob or Instapay proof). The idempotency key is the subscription and its latest period (plus an optional suffix the
 * reminders use, so a later reminder never points at an expired order), so asking twice returns the same pending order. CreateOrder prices it on the server and refuses a renewal made too early (`already_subscribed`).
 * Paying the order starts the next period through StartSubscriptionPeriod. Automatic renewal is not built (O-024).
 */
final class RenewSubscription
{
    public function __construct(private readonly CreateOrder $createOrder) {}

    public function __invoke(User $payer, Subscription $subscription, string $keySuffix = ''): Order
    {
        $latest = $subscription->latestPeriod();
        $learner = $subscription->learner;
        if ($latest === null || ! $learner instanceof User) {
            throw ValidationException::withMessages(['subscription' => __('subscriptions.not_renewable')]);
        }

        $market = Market::tryFrom((string) $latest->orderItem?->order?->market);
        if ($market === null) {
            throw ValidationException::withMessages(['subscription' => __('subscriptions.not_renewable')]);
        }

        return ($this->createOrder)($payer, $market, [new OrderLine(ProductType::Plan, $subscription->plan_id, $learner)], 'renew:'.$subscription->getKey().':'.$latest->sequence.($keySuffix === '' ? '' : ':'.$keySuffix));
    }
}
