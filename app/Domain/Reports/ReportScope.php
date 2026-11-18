<?php

declare(strict_types=1);

namespace App\Domain\Reports;

/** How much of a report one user may read: everything, only what belongs to their own courses/ledger, or nothing. */
enum ReportScope: string
{
    case All = 'all';
    case Own = 'own';
    case None = 'none';
}
