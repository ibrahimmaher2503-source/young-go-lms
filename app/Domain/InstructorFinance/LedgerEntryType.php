<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

enum LedgerEntryType: string
{
    case Earning = 'earning';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';
}
