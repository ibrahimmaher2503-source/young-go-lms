<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Events;

use App\Models\CourseRevision;

/** Dispatched after commit when a revision enters the review queue (ADR-002 CourseRevisionSubmitted). */
final class CourseRevisionSubmitted
{
    public function __construct(public readonly CourseRevision $revision) {}
}
