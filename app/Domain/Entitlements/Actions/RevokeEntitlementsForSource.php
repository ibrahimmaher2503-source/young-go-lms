<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\User;

/**
 * Revokes every still-open entitlement a single source reference granted (for example the access one order gave). Grants from
 * other sources or other orders for the same course stay untouched. Each revoke goes through RevokeEntitlement (permission,
 * audit, cache flush), so the actor needs the grant permission.
 */
final class RevokeEntitlementsForSource
{
    public function __construct(private readonly RevokeEntitlement $revoke) {}

    /**
     * @param  list<EntitlementSource>  $sources
     * @return int number of entitlements revoked
     */
    public function __invoke(User $actor, array $sources, string $sourceRef, string $reason): int
    {
        $count = 0;
        $rows = Entitlement::query()->where('source_ref', $sourceRef)
            ->whereIn('source_type', array_map(static fn (EntitlementSource $source): string => $source->value, $sources))
            ->where('status', '!=', Entitlement::REVOKED)->get();

        foreach ($rows as $entitlement) {
            ($this->revoke)($actor, $entitlement, $reason);
            $count++;
        }

        return $count;
    }
}
