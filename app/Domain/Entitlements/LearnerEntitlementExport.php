<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Exports a learner's entitlement records for privacy subject-access archives (staff-internal `reason` excluded),
 * keeping direct `entitlements` table access and section naming inside the Entitlements module (I01).
 */
final class LearnerEntitlementExport
{
    /** @return array<string, list<array<string, mixed>>> */
    public function sectionFor(int $learnerId): array
    {
        $rows = [];
        foreach (DB::table('entitlements')->where('learner_id', $learnerId)->get() as $row) {
            /** @var array<string, mixed> $data */
            $data = Arr::except((array) $row, ['reason']);
            $rows[] = $data;
        }

        return ['entitlements' => $rows];
    }
}
