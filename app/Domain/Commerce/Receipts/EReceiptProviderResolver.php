<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Receipts;

use App\Domain\Commerce\Contracts\EReceiptProvider;

/** Picks the e-receipt provider: `fake` only in local/testing, everything else (the default) is the disabled null driver. */
final class EReceiptProviderResolver
{
    public function __invoke(): EReceiptProvider
    {
        if ((string) config('e_receipts.driver', 'null') === 'fake' && app()->environment('local', 'testing')) {
            return new FakeEReceiptProvider;
        }

        return new NullEReceiptProvider;
    }
}
