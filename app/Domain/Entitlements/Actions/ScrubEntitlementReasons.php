<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Entitlements\Entitlement;

/**
 * Scrubs free-text grant/revoke reasons on entitlement rows during staging anonymization (YG-GAP-PRV-003)
 * while keeping direct `entitlements` table access inside the Entitlements module (I01).
 */
final class ScrubEntitlementReasons
{
    /** @param callable(string): string $scrub */
    public function __invoke(callable $scrub): int
    {
        $count = 0;
        Entitlement::query()->whereNotNull('reason')->orderBy('id')->chunkById(500, static function ($rows) use ($scrub, &$count): void {
            foreach ($rows as $row) {
                $value = (string) $row->reason;
                $clean = $scrub($value);
                if ($clean !== $value) {
                    Entitlement::query()->whereKey($row->getKey())->update(['reason' => $clean]);
                    $count++;
                }
            }
        });

        return $count;
    }
}
