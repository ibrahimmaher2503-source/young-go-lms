<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Events;

use App\Domain\Instructors\Models\InstructorAgreementAcceptance;

/** Dispatched after commit when an instructor accepted an agreement version (ADR-002). */
final class InstructorAgreementAccepted
{
    public function __construct(public readonly InstructorAgreementAcceptance $acceptance) {}
}
