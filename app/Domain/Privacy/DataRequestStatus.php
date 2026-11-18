<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

enum DataRequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Requested || $this === self::Approved;
    }

    public function label(): string
    {
        return __('privacy.status_'.$this->value);
    }
}
