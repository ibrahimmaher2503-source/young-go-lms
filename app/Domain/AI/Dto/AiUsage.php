<?php

declare(strict_types=1);

namespace App\Domain\AI\Dto;

final readonly class AiUsage
{
    public function __construct(public int $inputTokens, public int $cachedTokens, public int $outputTokens) {}
}
