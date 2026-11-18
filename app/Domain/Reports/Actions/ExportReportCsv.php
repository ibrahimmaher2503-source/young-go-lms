<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Reports\CsvCell;
use App\Domain\Reports\PreparedExport;
use App\Domain\Reports\ReportCatalog;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportTooLargeException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Streams a report as CSV (T-P9-06). `prepare()` authorises (`ReportCatalog`), scopes the filters, refuses an export over
 * `reports.max_rows` and writes the audit row `report.exported` (with the filters) BEFORE any byte is produced; if the audit fails
 * nothing is exported. `stream()` then writes a UTF-8 BOM (Arabic opens correctly in Excel), the translated heading and the rows
 * one by one (detail reports read in chunks), flushing as it goes. Every string cell goes through `CsvCell` (formula injection).
 * The domain writes to a stream resource; the HTTP layer wraps it in a download response.
 */
final class ExportReportCsv
{
    private const string BOM = "\xEF\xBB\xBF";

    public function __construct(private readonly ReportCatalog $catalog, private readonly AuditLogger $audit) {}

    /**
     * @param  resource  $stream
     * @return int rows written
     */
    public function __invoke(User $user, ReportKey $key, ReportFilters $requested, $stream): int
    {
        return $this->stream($this->prepare($user, $key, $requested), $stream);
    }

    /**
     * @throws AuthorizationException
     * @throws ReportTooLargeException
     * @throws RuntimeException when the audit row cannot be written
     */
    public function prepare(User $user, ReportKey $key, ReportFilters $requested): PreparedExport
    {
        $filters = $this->catalog->scope($user, $key, $requested);
        $definition = $this->catalog->definition($key);

        $rows = $definition->count($filters);
        $cap = Config::integer('reports.max_rows');
        if ($rows > $cap) {
            throw new ReportTooLargeException($rows, $cap);
        }

        if ($this->audit->record('report.exported', $user, (int) $user->getKey(), null, null, ['report' => $key->value, 'filters' => $filters->toAudit(), 'rows' => $rows]) === null) {
            throw new RuntimeException('Unable to record report export audit.');
        }

        return new PreparedExport($key, $definition, $filters, $rows);
    }

    /**
     * @param  resource  $stream
     */
    public function stream(PreparedExport $export, $stream): int
    {
        fwrite($stream, self::BOM);
        $this->put($stream, array_map(static fn (string $column): string => (string) __('reports.columns.'.$column), array_keys($export->definition->columns())));

        $written = 0;
        $cap = Config::integer('reports.max_rows');
        $chunk = max(1, Config::integer('reports.chunk'));

        foreach ($export->definition->rows($export->filters) as $row) {
            $this->put($stream, $row);
            $written++;

            if ($written % $chunk === 0) {
                fflush($stream);
            }
            if ($written >= $cap) {
                break;
            }
        }

        fflush($stream);

        return $written;
    }

    /**
     * @param  resource  $stream
     * @param  list<string|int|float|null>  $row
     */
    private function put($stream, array $row): void
    {
        fputcsv($stream, CsvCell::row($row), ',', '"', '');
    }
}
