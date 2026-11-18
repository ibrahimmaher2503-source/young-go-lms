<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\Order;
use App\Domain\Commerce\PaymentEvent;

/**
 * A verified online payment paid the order and access was granted. Dispatched once, after commit, and only for the
 * callback that fulfilled it (never for a duplicate). Hook for post-payment notifications (T-P9-05) and ledger source
 * events (T-P8-09); consumers must be idempotent by order id.
 */
final readonly class PaymentVerified
{
    public function __construct(public Order $order, public PaymentEvent $event) {}
}
