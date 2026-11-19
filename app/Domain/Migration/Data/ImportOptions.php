<?php

declare(strict_types=1);

namespace App\Domain\Migration\Data;

use Closure;

final readonly class ImportOptions
{
    /**
     * @param  (Closure(string, string, array<string, mixed>): void)|null  $beforeRow
     */
    public function __construct(
        public string $entity = 'all',
        public bool $dryRun = true,
        public int $chunkSize = 100,
        public ?int $resumeBatchId = null,
        public ?string $sourceSystem = null,
        public ?Closure $beforeRow = null,
    ) {}
}
