<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

/** How finance paid the instructor OUTSIDE the system. Only the method name is recorded, never account details. */
enum PayoutMethod: string
{
    case Bank = 'bank';
    case Wallet = 'wallet';
    case Cash = 'cash';
    case Other = 'other';
}
