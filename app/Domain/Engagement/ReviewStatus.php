<?php

declare(strict_types=1);

namespace App\Domain\Engagement;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Hidden = 'hidden';
}
