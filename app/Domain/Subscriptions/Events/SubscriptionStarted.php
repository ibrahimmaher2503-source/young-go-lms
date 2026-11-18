<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Events;

use App\Domain\Subscriptions\SubscriptionPeriod;

/** A first paid period of a subscription began. Dispatched once, after commit; consumers (ledger, T-P8-09) must be idempotent by period id. */
final readonly class SubscriptionStarted
{
    public function __construct(public SubscriptionPeriod $period) {}
}
