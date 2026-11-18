<?php

declare(strict_types=1);

namespace App\Domain\Support\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\User;

/** Opening a learner's 360 record is itself audited: who looked at whom, and which sensitive sections they could see. */
final class RecordLearner360View
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<string> $sections */
    public function __invoke(User $viewer, User $learner, array $sections): void
    {
        $this->audit->record('support.learner_360_viewed', $learner, (int) $viewer->getKey(), null, null, ['sections' => $sections]);
    }
}
