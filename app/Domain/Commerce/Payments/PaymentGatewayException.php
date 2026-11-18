<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

use RuntimeException;

/** A gateway call failed. The message is already redacted and safe to log; it never carries request bodies or credentials. */
class PaymentGatewayException extends RuntimeException
{
    public function __construct(string $message, public readonly string $failureCode = 'gateway_error')
    {
        parent::__construct($message);
    }
}
