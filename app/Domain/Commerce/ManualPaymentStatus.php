<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

enum ManualPaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
