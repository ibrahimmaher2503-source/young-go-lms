<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

/** PRD 17.4 states. New entries are `accrued`; the other states are moved by settlement (T-P8-10). */
enum LedgerStatus: string
{
    case Accrued = 'accrued';
    case Pending = 'pending';
    case Approved = 'approved';
    case Held = 'held';
    case Paid = 'paid';
    case Reversed = 'reversed';
}
