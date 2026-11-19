<?php

declare(strict_types=1);

namespace App\Domain\Migration\Data;

final readonly class VerificationReport
{
    /** @param list<EntityVerificationResult> $entities */
    public function __construct(
        public string $sourceSystem,
        public array $entities,
    ) {}

    public function passed(): bool
    {
        foreach ($this->entities as $entity) {
            if (! $entity->passed()) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_system' => $this->sourceSystem,
            'passed' => $this->passed(),
            'entities' => array_map(static fn (EntityVerificationResult $e): array => $e->toArray(), $this->entities),
        ];
    }
}
