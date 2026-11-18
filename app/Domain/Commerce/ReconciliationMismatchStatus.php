<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

enum ReconciliationMismatchStatus: string
{
    case Open = 'open';
    /** Finance looked at it and handled or accepted it outside this report. */
    case Reviewed = 'reviewed';
}
