<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    /** Gateway call in flight; set before the network call so a second click cannot send a second refund. */
    case Processing = 'processing';
    case Executed = 'executed';
    case Rejected = 'rejected';
    case Failed = 'failed';

    /** Counts against the refundable balance of the order. */
    public function holdsAmount(): bool
    {
        return $this !== self::Rejected && $this !== self::Failed;
    }

    public function isOpen(): bool
    {
        return $this === self::Requested || $this === self::Approved || $this === self::Processing;
    }
}
