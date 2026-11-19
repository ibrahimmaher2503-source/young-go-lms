<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use Illuminate\Database\Query\Builder;

final class RefundAccess
{
    /** @param list<string> $periodRefs */
    public static function orderLearners(string $orderReference, array $periodRefs): Builder
    {
        return Entitlement::query()->select(['learner_id', 'course_id'])
            ->where(static function ($grants) use ($orderReference, $periodRefs): void {
                $grants->where(static function ($direct) use ($orderReference): void {
                    $direct->where('source_ref', 'order:'.$orderReference)
                        ->whereIn('source_type', [EntitlementSource::Purchase, EntitlementSource::ManualPayment, EntitlementSource::Bundle]);
                })->orWhere(static function ($subscription) use ($periodRefs): void {
                    $subscription->where('source_type', EntitlementSource::Subscription)->whereIn('source_ref', $periodRefs);
                });
            })->toBase();
    }

    /** Check the current grant under the refund transaction's locks. */
    public static function hasActiveGrant(int $learnerId, int $courseId): bool
    {
        return Entitlement::query()->where('learner_id', $learnerId)->where('course_id', $courseId)
            ->withAccessState(Entitlement::ACTIVE)->lockForUpdate()->first() !== null;
    }
}
