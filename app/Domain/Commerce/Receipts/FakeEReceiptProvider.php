<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Receipts;

use App\Domain\Commerce\Contracts\EReceiptProvider;
use App\Domain\Commerce\EReceipt;
use LogicException;

/** Deterministic in-memory provider for local and test runs only: records what it was given and returns a fake document id. */
final class FakeEReceiptProvider implements EReceiptProvider
{
    /** @var list<string> receipt numbers, in submission order */
    public array $submitted = [];

    public function __construct()
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('The fake e-receipt provider is only available in local and testing environments.');
        }
    }

    public function key(): string
    {
        return 'fake';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function submit(EReceipt $receipt): EReceiptSubmission
    {
        $this->submitted[] = $receipt->number;

        return new EReceiptSubmission('fake', 'fake-doc-'.$receipt->number);
    }
}
