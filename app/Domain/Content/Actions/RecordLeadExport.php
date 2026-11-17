<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Audit entry for a CSV export of enquiries or subscribers (personal data leaving the system). */
final class RecordLeadExport
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, string $dataset): void
    {
        Gate::forUser($actor)->authorize('marketing.lead.view');

        $this->audit->record('lead.exported', $actor, (int) $actor->getKey(), null, null, ['dataset' => $dataset]);
    }
}
