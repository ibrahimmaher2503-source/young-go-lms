<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

use App\Domain\AI\UnavailableReason;
use RuntimeException;

/** The request was refused before any provider call: not eligible, or a thread limit was reached. No content is returned. */
final class AssistantRefused extends RuntimeException
{
    public const string THREAD_LIMIT = 'thread_limit';

    public const string SIMPLER_LIMIT = 'simpler_limit';

    public function __construct(public readonly string $kind, public readonly ?UnavailableReason $reason = null)
    {
        parent::__construct($reason !== null ? __('ai.unavailable.'.$reason->value) : __('ai.'.$kind));
    }

    public static function unavailable(UnavailableReason $reason): self
    {
        return new self('unavailable', $reason);
    }
}
