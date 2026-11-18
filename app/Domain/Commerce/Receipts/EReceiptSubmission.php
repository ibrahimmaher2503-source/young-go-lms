<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Receipts;

/** What an e-receipt provider returned for an accepted document. */
final readonly class EReceiptSubmission
{
    public function __construct(public string $provider, public string $documentUuid) {}
}
