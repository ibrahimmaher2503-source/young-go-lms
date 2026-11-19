<?php

declare(strict_types=1);

namespace App\Domain\Migration\Data;

use App\Domain\Migration\Enums\MigrationExceptionSeverity;

final readonly class ImportExceptionData
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $code,
        public MigrationExceptionSeverity $severity,
        public string $message,
        public array $context = [],
    ) {}
}
