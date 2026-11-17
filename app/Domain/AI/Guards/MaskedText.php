<?php

declare(strict_types=1);

namespace App\Domain\AI\Guards;

/** A question after PII masking: the text that may be stored and sent on, and how many items of each kind were hidden. */
final readonly class MaskedText
{
    /** @param  array<string, int>  $kinds  email, phone, id, address, name => count */
    public function __construct(public string $text, public array $kinds) {}

    public function containsPii(): bool
    {
        return $this->kinds !== [];
    }
}
