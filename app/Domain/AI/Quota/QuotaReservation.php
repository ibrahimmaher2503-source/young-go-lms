<?php

declare(strict_types=1);

namespace App\Domain\AI\Quota;

/** One reserved question unit (ED-010). Commit keeps it; refund gives it back. Settle it exactly once. */
final class QuotaReservation
{
    private bool $settled = false;

    /** @param  list<string>  $scopes */
    public function __construct(private readonly QuotaLedger $ledger, public readonly int $studentId, public readonly array $scopes, public readonly string $period) {}

    public function commit(): void
    {
        $this->settled = true;
    }

    public function refund(): void
    {
        if ($this->settled) {
            return;
        }

        $this->settled = true;
        $this->ledger->release($this);
    }
}
