<?php

declare(strict_types=1);

namespace App\Domain\Reports;

/**
 * One report. The page, the CSV export and the totals all read the same definition, so they cannot drift. Every method receives
 * filters already scoped by `ReportCatalog::scope()` for the signed-in user; a definition never looks at who is asking.
 *
 * Column types: text, int, money (decimal major units), percent, datetime (UTC).
 */
interface ReportDefinition
{
    public function key(): ReportKey;

    /** @return array<string, string> column key (lang `reports.columns.<key>`) => type */
    public function columns(): array;

    /** @return iterable<int, list<string|int|float|null>> rows in a stable order, streamed in chunks for the large ones */
    public function rows(ReportFilters $filters): iterable;

    public function count(ReportFilters $filters): int;

    /** @return list<array{key: string, currency: string|null, value: int|float, money: bool}> totals computed from the same rows */
    public function totals(ReportFilters $filters): array;
}
