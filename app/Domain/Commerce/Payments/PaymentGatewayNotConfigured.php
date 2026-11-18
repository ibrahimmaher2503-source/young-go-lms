<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

/** Raised by the Null gateway: no provider credentials exist yet (B-07). */
final class PaymentGatewayNotConfigured extends PaymentGatewayException
{
    public function __construct()
    {
        parent::__construct('No payment gateway is configured. Paymob credentials are an open owner item (B-07).', 'gateway_not_configured');
    }
}
