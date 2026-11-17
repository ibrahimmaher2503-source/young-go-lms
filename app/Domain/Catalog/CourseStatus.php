<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

enum CourseStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Private = 'private';
    case Upcoming = 'upcoming';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
