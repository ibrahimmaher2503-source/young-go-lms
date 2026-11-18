<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Receipts;

use RuntimeException;

/** Raised by the null e-receipt driver: no provider is enabled (O-023 is open). */
final class EReceiptNotEnabled extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('E-receipt submission is disabled until the owner decision O-023 is closed.');
    }
}
