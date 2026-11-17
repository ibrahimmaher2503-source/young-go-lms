<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use Carbon\CarbonInterface;

/** The answer to "may this learner open this?" with a reason code the UI can explain (Arch 11.2, ED-005). */
final readonly class AccessDecision
{
    private function __construct(public bool $allowed, public ?string $reason = null, public ?CarbonInterface $unlockAt = null, public ?string $detail = null) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason, ?CarbonInterface $unlockAt = null, ?string $detail = null): self
    {
        return new self(false, $reason, $unlockAt, $detail);
    }

    /** Learner-facing explanation; null when allowed. */
    public function message(): ?string
    {
        return $this->reason === null ? null : __('access.'.$this->reason, ['time' => $this->unlockAt?->isoFormat('LLL') ?? '', 'title' => $this->detail ?? '']);
    }
}
