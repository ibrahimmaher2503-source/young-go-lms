<?php

declare(strict_types=1);

/** Checkout rules that are not about payment gateways (those live in config/payments.php). */
return [
    // Minutes a cohort seat is held for an unpaid order before the hold stops counting and the expiry command gives it back.
    // A hold is never given back while the order has an open gateway session or a transfer proof under review (working default).
    'seat_hold_minutes' => 30,
];
