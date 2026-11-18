<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Events;

use App\Domain\Subscriptions\SubscriptionPeriod;

/** A renewal order was paid and a new period was added. Dispatched once, after commit; consumers must be idempotent by period id. */
final readonly class SubscriptionRenewed
{
    public function __construct(public SubscriptionPeriod $period) {}
}
