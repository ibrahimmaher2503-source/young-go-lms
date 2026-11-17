<?php

declare(strict_types=1);

namespace App\Domain\AI\Dto;

final readonly class ProviderHealth
{
    public function __construct(public bool $healthy, public string $message = '', public int $latencyMs = 0) {}
}
