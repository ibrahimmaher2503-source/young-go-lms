<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Events;

use App\Domain\Subscriptions\Subscription;

/** A subscription passed its grace period without renewal and was marked expired. Its plan entitlements have ended. */
final readonly class SubscriptionExpired
{
    public function __construct(public Subscription $subscription) {}
}
