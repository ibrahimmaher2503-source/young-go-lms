<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

enum EReceiptStatus: string
{
    /** Default: nothing has been sent to any tax authority or provider (O-023 is open). */
    case NotSubmitted = 'not_submitted';
    case Submitted = 'submitted';
    case Failed = 'failed';
}
