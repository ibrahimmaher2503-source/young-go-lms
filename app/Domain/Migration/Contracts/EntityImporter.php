<?php

declare(strict_types=1);

namespace App\Domain\Migration\Contracts;

use App\Domain\Migration\Data\EntityVerificationResult;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Support\LegacyIdMap;
use Closure;

interface EntityImporter
{
    public function entity(): LegacyEntity;

    public function sourceTable(): string;

    public function sourceIdColumn(): string;

    public function tableIsOptional(): bool;

    public function sourceCount(LegacySourceReader $reader): int;

    /**
     * @param  (Closure(list<array<string, mixed>>): void)  $onChunk
     */
    public function iterateSource(
        LegacySourceReader $reader,
        int $chunkSize,
        ?string $afterSourceId,
        Closure $onChunk,
    ): void;

    /** @param array<string, mixed> $row */
    public function extractSourceId(array $row): string;

    /**
     * @param  array<string, mixed>  $row
     * @return array<array-key, mixed>
     */
    public function checksumPayload(array $row): array;

    /** @param array<string, mixed> $row */
    public function importRow(array $row, ImportContext $context): RowImportResult;

    public function verifyTarget(LegacySourceReader $reader, LegacyIdMap $idMap, int $sampleSize, string $sourceSystem): EntityVerificationResult;
}
