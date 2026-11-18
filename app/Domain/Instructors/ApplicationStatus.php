<?php

declare(strict_types=1);

namespace App\Domain\Instructors;

enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
