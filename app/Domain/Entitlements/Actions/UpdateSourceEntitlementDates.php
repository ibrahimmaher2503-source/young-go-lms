<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Updates the access window of all non-revoked entitlements created by a single source reference (e.g. a subscription period).
 * Keeps every entitlements table write inside the Entitlements module (I01) and delegates audit + cache flush to UpdateEntitlement.
 */
final class UpdateSourceEntitlementDates
{
    public function __construct(private readonly UpdateEntitlement $update) {}

    public function __invoke(
        User $actor,
        EntitlementSource $source,
        string $sourceRef,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        string $reason,
    ): int {
        $count = 0;
        $rows = Entitlement::query()
            ->where('source_type', $source->value)
            ->where('source_ref', $sourceRef)
            ->where('status', '!=', Entitlement::REVOKED)
            ->get();

        foreach ($rows as $entitlement) {
            ($this->update)($actor, $entitlement, $startsAt, $endsAt, $reason);
            $count++;
        }

        return $count;
    }
}
