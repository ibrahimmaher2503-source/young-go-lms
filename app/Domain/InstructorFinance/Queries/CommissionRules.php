<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Queries;

use App\Domain\InstructorFinance\CommissionRule;
use App\Domain\InstructorFinance\ResolvedRate;
use App\Domain\InstructorFinance\SaleType;
use DateTimeInterface;

/**
 * The one place that picks the commission rule for a sale. Precedence: course + instructor, course only, instructor only,
 * a global rule (no course, no instructor: the editable default), then the config fallback (70% by default, O-008/O-010). Only rules in force on the sale date count
 * (both ends inclusive); fixed-amount rules only count when their currency is the sale currency; within a tier the latest
 * `effective_from` (then the newest row) wins. Percentage rules apply to individual and bundle sales; subscription rules are
 * fixed amounts per student-course-period.
 */
final class CommissionRules
{
    public function resolve(int $instructorId, int $courseId, SaleType $type, string $currency, DateTimeInterface $at): ResolvedRate
    {
        $day = $at->format('Y-m-d');

        $candidates = CommissionRule::query()
            ->where('sale_type', $type->value)
            ->whereDate('effective_from', '<=', $day)
            ->where(static fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day))
            ->where(static fn ($q) => $q->whereNull('currency')->orWhere('currency', $currency))
            ->where(static fn ($q) => $q->whereNull('course_id')->orWhere('course_id', $courseId))
            ->where(static fn ($q) => $q->whereNull('instructor_id')->orWhere('instructor_id', $instructorId))
            ->get();

        $tier = static fn (CommissionRule $r): int => ($r->course_id !== null ? 2 : 0) + ($r->instructor_id !== null ? 1 : 0);
        $best = $candidates
            ->sort(static fn (CommissionRule $a, CommissionRule $b): int => [$tier($b), $b->effective_from->getTimestamp(), $b->id] <=> [$tier($a), $a->effective_from->getTimestamp(), $a->id])
            ->first();

        if ($best instanceof CommissionRule) {
            return new ResolvedRate($best, $best->rate_bps, $best->fixed_amount_minor, ResolvedRate::SOURCE_RULE);
        }

        $fixed = max(0, (int) config('commissions.default_subscription_fixed_minor', 0));
        if ($type === SaleType::Subscription && $fixed > 0) {
            return new ResolvedRate(null, null, $fixed, ResolvedRate::SOURCE_DEFAULT);
        }

        return new ResolvedRate(null, max(0, (int) config('commissions.default_rate_bps.'.$type->value, 0)), null, ResolvedRate::SOURCE_DEFAULT);
    }
}
