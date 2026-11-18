<?php

declare(strict_types=1);

namespace App\Domain\Reports;

/** An authorised, size-checked and audited export that is ready to be written to a stream. */
final class PreparedExport
{
    public function __construct(
        public readonly ReportKey $key,
        public readonly ReportDefinition $definition,
        public readonly ReportFilters $filters,
        public readonly int $rows,
    ) {}

    public function filename(): string
    {
        return 'report-'.str_replace('_', '-', $this->key->value).'-'.now()->utc()->format('Ymd-His').'.csv';
    }
}
