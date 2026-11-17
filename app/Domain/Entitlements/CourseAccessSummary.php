<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

/** Counts of access grants by source and state, for one course or all courses (PRD 18, checklist 5). */
final class CourseAccessSummary
{
    /**
     * Active means current right now; expired covers rows swept as expired and rows whose end time has passed.
     *
     * @return array{active: int, expired: int, revoked: int, by_source: array<string, array{active: int, expired: int, revoked: int}>}
     */
    public static function for(?int $courseId = null): array
    {
        $summary = ['active' => 0, 'expired' => 0, 'revoked' => 0, 'by_source' => []];

        $query = Entitlement::query();
        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        foreach ($query->get(['source_type', 'status', 'starts_at', 'ends_at']) as $row) {
            $state = match (true) {
                $row->status === Entitlement::REVOKED => 'revoked',
                $row->status === Entitlement::EXPIRED, $row->ends_at !== null && $row->ends_at->lte(now()) => 'expired',
                default => 'active',
            };
            $summary[$state]++;
            $source = $row->source_type->value;
            $summary['by_source'][$source] ??= ['active' => 0, 'expired' => 0, 'revoked' => 0];
            $summary['by_source'][$source][$state]++;
        }

        return $summary;
    }
}
