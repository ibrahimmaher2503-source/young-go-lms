<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Receipts;

use App\Domain\Commerce\Contracts\EReceiptProvider;
use App\Domain\Commerce\EReceipt;

/** The default: disabled. It never talks to anyone and refuses every submission. */
final class NullEReceiptProvider implements EReceiptProvider
{
    public function key(): string
    {
        return 'null';
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function submit(EReceipt $receipt): EReceiptSubmission
    {
        throw new EReceiptNotEnabled;
    }
}
