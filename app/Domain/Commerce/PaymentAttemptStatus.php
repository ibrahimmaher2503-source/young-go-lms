<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

enum PaymentAttemptStatus: string
{
    /** Row reserved, gateway not answered yet. */
    case Initiated = 'initiated';
    /** Gateway session exists, payer may still pay. */
    case Created = 'created';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** Holds the one open slot of its order. */
    public function isOpen(): bool
    {
        return $this === self::Initiated || $this === self::Created;
    }
}
