<?php

declare(strict_types=1);

namespace App\Domain\AI\Dto;

final readonly class AiCompletionResult
{
    public function __construct(
        public string $content,
        public AiUsage $usage,
        public int $costMinor,
        public int $latencyMs,
        public string $providerRequestId,
        public string $finishReason,
        public string $model = 'unknown',
    ) {}
}
