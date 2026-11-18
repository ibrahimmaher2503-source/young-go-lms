<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

use RuntimeException;

/** The callback signature is missing, malformed or wrong. Callers reject the request and change nothing. */
final class InvalidPaymentSignature extends RuntimeException
{
    public function __construct(string $message = 'Payment callback signature is not valid.')
    {
        parent::__construct($message);
    }
}
