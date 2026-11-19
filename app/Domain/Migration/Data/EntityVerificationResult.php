<?php

declare(strict_types=1);

namespace App\Domain\Migration\Data;

final readonly class EntityVerificationResult
{
    /**
     * @param  list<SampleCheckResult>  $samples
     * @param  list<string>  $notes
     */
    public function __construct(
        public string $entity,
        public string $sourceTable,
        public int $sourceCount,
        public int $mappedCount,
        public int $skippedCount,
        public int $rejectedCount,
        public int $targetFoundCount,
        public bool $countsReconcile,
        public int $samplesChecked,
        public int $samplesPassed,
        public int $samplesFailed,
        public array $samples = [],
        public array $notes = [],
    ) {}

    public function passed(): bool
    {
        return $this->countsReconcile && $this->samplesFailed === 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity,
            'source_table' => $this->sourceTable,
            'source_count' => $this->sourceCount,
            'mapped_count' => $this->mappedCount,
            'skipped_count' => $this->skippedCount,
            'rejected_count' => $this->rejectedCount,
            'target_found_count' => $this->targetFoundCount,
            'counts_reconcile' => $this->countsReconcile,
            'samples_checked' => $this->samplesChecked,
            'samples_passed' => $this->samplesPassed,
            'samples_failed' => $this->samplesFailed,
            'passed' => $this->passed(),
            'samples' => array_map(static fn (SampleCheckResult $s): array => $s->toArray(), $this->samples),
            'notes' => $this->notes,
        ];
    }
}
