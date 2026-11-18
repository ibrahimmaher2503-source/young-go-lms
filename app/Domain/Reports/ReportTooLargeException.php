<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use RuntimeException;

/** The export would have more rows than `reports.max_rows`. Nothing is written; the user narrows the filters. */
final class ReportTooLargeException extends RuntimeException
{
    public function __construct(public readonly int $rows, public readonly int $cap)
    {
        parent::__construct("Report export has {$rows} rows, the cap is {$cap}.");
    }
}
