<?php

declare(strict_types=1);

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\ModerationClassifier;
use App\Domain\AI\Guards\ModerationCategory;

/** Deterministic moderation for tests: queue verdicts with respondWith(), read the texts it was asked about with inspected(). */
final class FakeModerationClassifier implements ModerationClassifier
{
    /** @var list<ModerationCategory|null> */
    private array $queue = [];

    /** @var list<string> */
    private array $inspected = [];

    public function respondWith(?ModerationCategory $category): self
    {
        $this->queue[] = $category;

        return $this;
    }

    /** @return list<string> */
    public function inspected(): array
    {
        return $this->inspected;
    }

    public function reset(): self
    {
        $this->queue = $this->inspected = [];

        return $this;
    }

    public function classify(string $maskedText): ?ModerationCategory
    {
        $this->inspected[] = $maskedText;

        return $this->queue === [] ? null : array_shift($this->queue);
    }
}
