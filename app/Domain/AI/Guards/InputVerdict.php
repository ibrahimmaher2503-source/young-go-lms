<?php

declare(strict_types=1);

namespace App\Domain\AI\Guards;

/** Result of the input guards for one question: the masked text, and the guard that stopped it (if any) with its fixed message. */
final readonly class InputVerdict
{
    /** @param  array<string, int>  $piiKinds */
    public function __construct(
        public string $text,
        public array $piiKinds,
        public ?GuardKind $blocked = null,
        public ?ModerationCategory $category = null,
        public ?int $page = null,
        public string $message = '',
    ) {}

    public function isBlocked(): bool
    {
        return $this->blocked !== null;
    }
}
