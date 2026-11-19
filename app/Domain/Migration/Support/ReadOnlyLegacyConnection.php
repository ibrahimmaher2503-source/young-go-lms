<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use App\Domain\Migration\Contracts\LegacySourceReader;
use App\Domain\Migration\Exceptions\LegacyWriteForbiddenException;
use App\Domain\Migration\Exceptions\MigrationConfigurationException;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use WeakMap;

/**
 * Wraps the dedicated `legacy` DB connection and enforces read-only execution at both SQL hook and PDO driver levels.
 */
final class ReadOnlyLegacyConnection implements LegacySourceReader
{
    /** @var WeakMap<Connection, bool>|null */
    private static ?WeakMap $guarded = null;

    private readonly string $connectionName;

    public function __construct(?string $connectionName = null)
    {
        $resolved = $connectionName ?? Config::string('legacy_migration.connection', 'legacy');
        if ($resolved === '') {
            throw new MigrationConfigurationException('Legacy connection name cannot be empty.');
        }

        if ($resolved === Config::string('database.default')) {
            throw new MigrationConfigurationException('Legacy connection must be distinct from the default target database connection.');
        }

        if (! is_array(Config::get('database.connections.'.$resolved))) {
            throw new MigrationConfigurationException("Legacy connection '{$resolved}' is not configured in config/database.php.");
        }

        $this->connectionName = $resolved;
    }

    public function connectionName(): string
    {
        return $this->connectionName;
    }

    public function connection(): Connection
    {
        $connection = DB::connection($this->connectionName);
        if (! $connection instanceof Connection) {
            throw new MigrationConfigurationException("Connection '{$this->connectionName}' did not resolve to a database Connection.");
        }

        $this->ensureGuarded($connection);

        return $connection;
    }

    public function tableExists(string $table): bool
    {
        return $this->connection()->getSchemaBuilder()->hasTable($table);
    }

    public function columns(string $table): array
    {
        if (! $this->tableExists($table)) {
            return [];
        }

        return array_values(array_map(strval(...), $this->connection()->getSchemaBuilder()->getColumnListing($table)));
    }

    public function count(string $table, ?Closure $scope = null): int
    {
        $query = $this->connection()->table($table);
        if ($scope !== null) {
            $scope($query);
        }

        return (int) $query->count();
    }

    public function chunkById(
        string $table,
        string $idColumn,
        int $chunkSize,
        ?string $afterId,
        Closure $callback,
        ?Closure $scope = null,
    ): void {
        $chunkSize = max(1, $chunkSize);
        $cursor = $afterId;

        while (true) {
            $query = $this->connection()->table($table);
            if ($scope !== null) {
                $scope($query);
            }

            if ($cursor !== null) {
                $bound = ctype_digit($cursor) ? (int) $cursor : $cursor;
                $query->where($idColumn, '>', $bound);
            }

            $rows = $query->orderBy($idColumn)->limit($chunkSize)->get();
            if ($rows->isEmpty()) {
                break;
            }

            $batch = $rows->map(static fn (object $row): array => get_object_vars($row))->values()->all();
            $callback($batch);

            $last = end($batch);
            if (! is_array($last) || ! array_key_exists($idColumn, $last)) {
                break;
            }

            $cursor = (string) $last[$idColumn];
            if (count($batch) < $chunkSize) {
                break;
            }
        }
    }

    public function findById(string $table, string $idColumn, int|string $id): ?array
    {
        $row = $this->connection()->table($table)->where($idColumn, $id)->first();

        return $row === null ? null : get_object_vars($row);
    }

    public function selectAll(string $table, ?Closure $scope = null): array
    {
        $query = $this->connection()->table($table);
        if ($scope !== null) {
            $scope($query);
        }

        return $query->get()->map(static fn (object $row): array => get_object_vars($row))->values()->all();
    }

    public function selectSample(string $table, string $idColumn, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->connection()->table($table)->whereIn($idColumn, $ids)->get();
        $byId = [];
        foreach ($rows as $row) {
            $vars = get_object_vars($row);
            $key = (string) ($vars[$idColumn] ?? '');
            if ($key !== '') {
                $byId[$key] = $vars;
            }
        }

        return $byId;
    }

    public static function assertReadOnlySql(string $sql): void
    {
        // Strip leading comments so "/* trick */ INSERT ..." cannot pass prefix checks.
        $cleaned = preg_replace('/^\s*(?:\/\*[\s\S]*?\*\/\s*|--[^\r\n]*[\r\n]\s*)*/', '', $sql) ?? $sql;
        $trimmed = ltrim($cleaned);

        if ($trimmed === '') {
            throw new LegacyWriteForbiddenException('Empty SQL query rejected on read-only legacy connection.');
        }

        $withoutTrailingSemicolon = rtrim($trimmed, "; \t\n\r\0\x0B");
        if (str_contains($withoutTrailingSemicolon, ';')) {
            throw new LegacyWriteForbiddenException('Multi-statement SQL is forbidden on the read-only legacy connection.');
        }

        $upper = strtoupper($withoutTrailingSemicolon);
        $allowedPrefixes = [
            'SELECT ',
            'SELECT(',
            'WITH ',
            'SHOW ',
            'DESCRIBE ',
            'EXPLAIN ',
            'PRAGMA TABLE_INFO',
            'PRAGMA TABLE_XINFO',
            'PRAGMA TABLE_LIST',
            'PRAGMA QUERY_ONLY',
            'SET SESSION TRANSACTION READ ONLY',
            'SET TRANSACTION READ ONLY',
        ];

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($upper, $prefix)) {
                return;
            }
        }

        throw new LegacyWriteForbiddenException('Write or DDL query rejected on read-only legacy connection: '.$upper);
    }

    private function ensureGuarded(Connection $connection): void
    {
        self::$guarded ??= new WeakMap;
        if (isset(self::$guarded[$connection])) {
            return;
        }

        self::$guarded[$connection] = true;

        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            $connection->getPdo()->exec('PRAGMA query_only = ON');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $connection->getPdo()->exec('SET SESSION TRANSACTION READ ONLY');
        }

        if (Config::boolean('legacy_migration.enforce_read_only', true)) {
            $connection->beforeExecuting(static function (string $query): void {
                self::assertReadOnlySql($query);
            });
        }
    }
}
