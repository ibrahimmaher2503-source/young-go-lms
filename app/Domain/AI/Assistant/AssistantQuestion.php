<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

/** What the client may send. It never carries a lesson, course or student id: the server resolves those. */
final readonly class AssistantQuestion
{
    public function __construct(
        public string $question,
        public string $locale,
        public ?int $videoPosition = null,
        public ?int $conversationId = null,
        public AssistantMode $mode = AssistantMode::Normal,
    ) {}
}
