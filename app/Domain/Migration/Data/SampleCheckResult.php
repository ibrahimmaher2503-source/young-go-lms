<?php

declare(strict_types=1);

namespace App\Domain\Migration\Data;

final readonly class SampleCheckResult
{
    /** @param list<string> $mismatches */
    public function __construct(
        public string $sourceId,
        public ?int $targetId,
        public bool $passed,
        public array $mismatches = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_id' => $this->sourceId,
            'target_id' => $this->targetId,
            'passed' => $this->passed,
            'mismatches' => $this->mismatches,
        ];
    }
}
