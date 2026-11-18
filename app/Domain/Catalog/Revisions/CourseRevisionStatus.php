<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions;

enum CourseRevisionStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Discarded = 'discarded';

    /** A draft or pending revision is the one open revision of its course. */
    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Pending;
    }
}
