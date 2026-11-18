<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Contracts;

use App\Domain\Commerce\EReceipt;
use App\Domain\Commerce\Receipts\EReceiptNotEnabled;
use App\Domain\Commerce\Receipts\EReceiptSubmission;

/**
 * Sends a receipt to an Egyptian e-receipt/e-invoice provider (YG-GAP-PAY-005). Whether this is required, and who does it, is
 * the open owner item O-023, so the default driver is disabled and never pretends to submit.
 */
interface EReceiptProvider
{
    public function key(): string;

    /** False for the null driver: callers refuse before anything is stored or sent. */
    public function isEnabled(): bool;

    /** @throws EReceiptNotEnabled */
    public function submit(EReceipt $receipt): EReceiptSubmission;
}
