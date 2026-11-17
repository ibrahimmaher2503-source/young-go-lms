<?php

declare(strict_types=1);

namespace App\Domain\AI;

final readonly class AssistantAvailability
{
    private function __construct(public ?UnavailableReason $reason) {}

    public static function available(): self
    {
        return new self(null);
    }

    public static function unavailable(UnavailableReason $reason): self
    {
        return new self($reason);
    }

    public function isAvailable(): bool
    {
        return $this->reason === null;
    }

    /** Learner-facing explanation; null when available. */
    public function message(): ?string
    {
        return $this->reason === null ? null : __('ai.unavailable.'.$this->reason->value);
    }
}
