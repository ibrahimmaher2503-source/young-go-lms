<?php

declare(strict_types=1);

namespace App\Domain\AI\Dto;

/** One provider call. Carries an opaque student hash, never the student's identity. */
final readonly class AiCompletionRequest
{
    /**
     * @param  list<array{role: string, content: string}>  $turns
     * @param  array<string, mixed>  $responseSchema
     */
    public function __construct(
        public int $promptVersion,
        public string $cacheablePrefix,
        public array $turns,
        public int $maxTokens,
        public array $responseSchema,
        public int $timeoutSeconds,
        public string $userHash,
        public string $idempotencyKey,
    ) {}
}
