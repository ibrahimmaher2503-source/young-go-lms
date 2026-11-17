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
    ) {}

    public function answered(): bool
    {
        return $this->outcome === AssistantOutcome::Answered;
    }
}
