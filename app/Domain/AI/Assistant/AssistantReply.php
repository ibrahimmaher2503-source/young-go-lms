<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

final readonly class AssistantReply
{
    /** @param  list<int>  $sourcePages */
    public function __construct(
        public AssistantOutcome $outcome,
        public string $message,
        public array $sourcePages,
        public string $language,
        public bool $quoteVerified,
        public int $messageId,
        public int $conversationId,
        public int $documentId,
        /** The input guard that stopped the question (moderation, safeguarding, injection, quiz_guard), else null. */
        public ?string $guard = null,
        public bool $cacheHit = false,
    ) {}

    public function answered(): bool
    {
        return $this->outcome === AssistantOutcome::Answered;
    }
}
