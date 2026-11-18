<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions;

/** Stored state of a subscription. Cancelled is a flag (`cancelled_at`) because a cancelled one keeps access to period end. */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
}
