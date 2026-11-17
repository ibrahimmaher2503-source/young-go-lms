<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Disputed = 'disputed';

    /** Once money has been taken the totals are frozen; only an audited adjustment may change them. */
    public function locksTotals(): bool
    {
        return in_array($this, [self::Paid, self::Refunded, self::PartiallyRefunded, self::Disputed], true);
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Failed, self::Cancelled],
            self::Paid => [self::Refunded, self::PartiallyRefunded, self::Disputed],
            self::PartiallyRefunded => [self::Refunded, self::Disputed],
            self::Disputed => [self::Paid, self::Refunded],
            self::Failed, self::Cancelled, self::Refunded => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }
}
