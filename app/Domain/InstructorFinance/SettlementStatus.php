<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

/** Draft (entries reserved as pending) -> approved -> paid (every instructor line recorded as paid); or cancelled before any payout. */
enum SettlementStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
