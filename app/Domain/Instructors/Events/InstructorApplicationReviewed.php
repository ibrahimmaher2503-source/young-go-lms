<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Events;

use App\Domain\Instructors\Models\InstructorApplication;

/** Dispatched after commit when an application was approved or rejected (ADR-002). Carries no personal data beyond the row. */
final class InstructorApplicationReviewed
{
    public function __construct(public readonly InstructorApplication $application) {}
}
