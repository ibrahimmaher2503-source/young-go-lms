<?php

declare(strict_types=1);

namespace App\Domain\AI\Quota;

/** One in-flight monthly budget reservation (T-P7-02). Release it once after usage is persisted or on failure. */
final class BudgetReservation
{
    private bool $released = false;

    /** @param  list<int>  $ids */
    public function __construct(
        private readonly BudgetGate $gate,
        public readonly array $ids,
    ) {}

    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->released = true;

        if ($this->ids !== []) {
            $this->gate->release($this);
        }
    }
}
