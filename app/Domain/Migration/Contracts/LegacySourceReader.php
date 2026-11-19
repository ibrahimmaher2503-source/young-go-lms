<?php

declare(strict_types=1);

namespace App\Domain\Migration\Contracts;

use Closure;
use Illuminate\Database\Query\Builder;

/**
 * Strictly read-only interface to the configured legacy source database connection.
 */
interface LegacySourceReader
{
    public function connectionName(): string;

    public function tableExists(string $table): bool;

    /** @return list<string> */
    public function columns(string $table): array;

    /** @param (Closure(Builder): void)|null $scope */
    public function count(string $table, ?Closure $scope = null): int;

    /**
     * @param  (Closure(list<array<string, mixed>>): void)  $callback
     * @param  (Closure(Builder): void)|null  $scope
     */
    public function chunkById(
        string $table,
        string $idColumn,
        int $chunkSize,
        ?string $afterId,
        Closure $callback,
        ?Closure $scope = null,
    ): void;

    /** @return array<string, mixed>|null */
    public function findById(string $table, string $idColumn, int|string $id): ?array;

    /**
     * @param  (Closure(Builder): void)|null  $scope
     * @return list<array<string, mixed>>
     */
    public function selectAll(string $table, ?Closure $scope = null): array;

    /**
     * @param  list<int|string>  $ids
     * @return array<string, array<string, mixed>>
     */
    public function selectSample(string $table, string $idColumn, array $ids): array;
}
