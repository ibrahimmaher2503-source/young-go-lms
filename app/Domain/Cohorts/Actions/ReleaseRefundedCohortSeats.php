<?php

declare(strict_types=1);

namespace App\Domain\Cohorts\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortMember;
use App\Domain\Cohorts\CohortSeatHold;
use App\Domain\Commerce\Order;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\RefundAccess;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Models\User;
use Closure;
use RuntimeException;

/** Releases commerce-funded members within the full refund transaction. Staff handle any later waitlist promotion. */
final class ReleaseRefundedCohortSeats
{
    public function __construct(private readonly AuditLogger $audit, private readonly EntitlementService $entitlements) {}

    /** @param Closure(): int $revokeAccess */
    public function __invoke(User $actor, Order $order, Closure $revokeAccess): int
    {
        $items = $order->items()->select('id');
        $periodRefs = SubscriptionPeriod::query()->whereIn('order_item_id', $items)->get()
            ->map(static fn (SubscriptionPeriod $period): string => $period->entitlementRef())->all();
        $learnersByCourse = RefundAccess::orderLearners($order->reference, $periodRefs)
            ->union(CohortSeatHold::query()->join('cohorts', 'cohorts.id', '=', 'cohort_seat_holds.cohort_id')
                ->whereIn('order_item_id', $items)->where(static fn ($hold) => $hold->where('cohort_seat_holds.status', CohortSeatHold::CONVERTED)
                ->orWhere('cohort_seat_holds.release_reason', ConvertSeatHolds::FULL_AT_PAYMENT))
                ->select(['cohort_seat_holds.learner_id', 'cohorts.course_id'])->toBase())
            ->get()->groupBy('course_id');

        // Serialize revocation with other refunds and conversions for affected cohorts.
        $cohorts = Cohort::query()->whereIn('course_id', $learnersByCourse->keys())->orderBy('id')->lockForUpdate()->get();
        $revoked = $revokeAccess();

        foreach ($cohorts as $cohort) {
            $members = $cohort->members()->whereIn('status', [CohortMember::SEATED, CohortMember::WAITLISTED])->whereNotNull('seat_order_item_id')
                ->whereIn('learner_id', $learnersByCourse[$cohort->course_id]->pluck('learner_id'))->orderBy('id')->lockForUpdate()->get();

            foreach ($members as $member) {
                if (RefundAccess::hasActiveGrant($member->learner_id, $cohort->course_id)) {
                    continue;
                }

                $previous = $member->status;
                $member->update(['status' => CohortMember::REMOVED, 'removed_at' => now()]);
                if ($this->audit->record('cohort.member_removed', $cohort, (int) $actor->getKey(), ['status' => $previous], [
                    'learner_id' => $member->learner_id, 'refund_order' => $order->reference,
                ]) === null) {
                    throw new RuntimeException('Unable to record cohort audit.');
                }
            }
        }

        $this->entitlements->flush();

        return $revoked;
    }
}
