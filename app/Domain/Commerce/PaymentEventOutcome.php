<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

enum PaymentEventOutcome: string
{
    /** The order was paid and access granted. */
    case Fulfilled = 'fulfilled';
    /** The attempt was closed as failed; the order stays pending so the payer can try again. */
    case AttemptFailed = 'attempt_failed';
    /** Valid but needs no change (pending, out of order, already handled). */
    case Ignored = 'ignored';
    /** Valid money event that must not change the order automatically; finance reviews it. */
    case Flagged = 'flagged';
    /** Valid signature but no attempt of ours carries this reference. */
    case Unmatched = 'unmatched';

    public function needsReview(): bool
    {
        return $this === self::Flagged || $this === self::Unmatched;
    }
}
