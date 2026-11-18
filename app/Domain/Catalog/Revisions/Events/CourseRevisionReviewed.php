<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Events;

use App\Models\CourseRevision;

/** Dispatched after commit when a pending revision was approved (applied) or rejected (ADR-002 CourseRevisionApproved/Rejected). */
final class CourseRevisionReviewed
{
    public function __construct(public readonly CourseRevision $revision, public readonly bool $approved, public readonly ?string $reason) {}
}
