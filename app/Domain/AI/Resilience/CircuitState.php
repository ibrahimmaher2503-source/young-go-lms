<?php

declare(strict_types=1);

namespace App\Domain\AI\Resilience;

/** Snapshot of one provider route's breaker, for the health widget. */
final readonly class CircuitState
{
    public const string CLOSED = 'closed';

    public const string OPEN = 'open';

    public const string HALF_OPEN = 'half_open';

    public function __construct(
        public string $state,
        public int $failures,
        public ?int $retryAt,
        public ?int $openedAt,
    ) {}

    public function isOpen(): bool
    {
        return $this->state === self::OPEN;
    }
}
