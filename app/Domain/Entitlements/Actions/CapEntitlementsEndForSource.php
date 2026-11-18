<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use Carbon\CarbonInterface;

/**
 * Caps open entitlements for a single source reference so they end no later than `$maxEndsAt`
 * (for example trimming post-period subscription grace days when a subscription is cancelled, YG-GAP-SUB-004).
 */
final class CapEntitlementsEndForSource
{
    public function __construct(private readonly EntitlementService $service) {}

    public function __invoke(EntitlementSource $source, string $sourceRef, CarbonInterface $maxEndsAt): int
    {
        $updated = Entitlement::query()
            ->where('source_type', $source->value)
            ->where('source_ref', $sourceRef)
            ->where('status', Entitlement::ACTIVE)
            ->where('ends_at', '>', $maxEndsAt)
            ->update(['ends_at' => $maxEndsAt]);

        if ($updated > 0) {
            $this->service->flush();
        }

        return $updated;
    }
}
