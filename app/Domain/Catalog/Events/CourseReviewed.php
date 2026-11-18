<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Events;

use App\Models\Course;

/** Dispatched after commit when a pending course was approved (published) or rejected (returned to draft). ADR-002. */
final class CourseReviewed
{
    public function __construct(public readonly Course $course, public readonly bool $approved, public readonly ?string $reason) {}
}
