<?php

declare(strict_types=1);

namespace App\Domain\Cohorts\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortMember;
use App\Domain\Cohorts\CohortSeatHold;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns the seat holds of a paid order into real seats (a seated `CohortMember`). Runs inside the payment transition and never
 * throws for capacity: the money is already taken. A hold that expired and was given back is taken again if there is room; if the
 * cohort filled up meanwhile the learner goes to the front of that cohort waitlist and the miss is audited as
 * `cohort.seat_conversion_failed` so staff can resolve it. The enrolment window is not checked (the learner reserved in time).
 * Idempotent: a converted hold is skipped. Returns how many learners were seated.
 */
final class ConvertSeatHolds
{
    public const string FULL_AT_PAYMENT = 'full_at_payment';

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  list<int>  $orderItemIds */
    public function __invoke(array $orderItemIds, ?User $actor = null): int
    {
        if ($orderItemIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($orderItemIds, $actor): int {
            $seated = 0;
            $holds = CohortSeatHold::query()->whereIn('order_item_id', $orderItemIds)->whereIn('status', [CohortSeatHold::HELD, CohortSeatHold::RELEASED])
                ->orderBy('id')->lockForUpdate()->get();
            $actorId = $actor === null ? null : (int) $actor->getKey();

            foreach ($holds as $hold) {
                // A hold released because the order was cancelled or failed is final; only an expired one can still be honoured.
                if ($hold->status === CohortSeatHold::RELEASED && $hold->release_reason !== ReleaseSeatHolds::EXPIRED) {
                    continue;
                }
                $cohort = Cohort::query()->whereKey($hold->cohort_id)->lockForUpdate()->firstOrFail();
                $member = CohortMember::query()->where('cohort_id', $cohort->getKey())->where('learner_id', $hold->learner_id)->first();
                $learner = User::query()->findOrFail($hold->learner_id);

                if ($member?->status !== CohortMember::SEATED && $cohort->isFull($learner)) {
                    $this->waitlist($cohort, $hold, $member);
                    $hold->forceFill(['status' => CohortSeatHold::RELEASED, 'released_at' => now(), 'release_reason' => self::FULL_AT_PAYMENT])->save();
                    $this->record('cohort.seat_conversion_failed', $cohort, $actorId, ['learner_id' => $hold->learner_id, 'order_item_id' => $hold->order_item_id]);

                    continue;
                }

                if ($member?->status !== CohortMember::SEATED) {
                    $member ??= new CohortMember(['cohort_id' => $cohort->getKey(), 'learner_id' => $hold->learner_id]);
                    $member->fill(['status' => CohortMember::SEATED, 'seated_at' => now(), 'queued_at' => null, 'removed_at' => null])->save();
                    $seated++;
                }
                $hold->forceFill(['status' => CohortSeatHold::CONVERTED, 'converted_at' => now(), 'release_reason' => null, 'released_at' => null])->save();
                $this->record('cohort.seat_converted', $cohort, $actorId, ['learner_id' => $hold->learner_id, 'order_item_id' => $hold->order_item_id]);
            }

            return $seated;
        });
    }

    private function waitlist(Cohort $cohort, CohortSeatHold $hold, ?CohortMember $member): void
    {
        if ($member?->status === CohortMember::WAITLISTED) {
            return;
        }
        $first = CohortMember::query()->where('cohort_id', $cohort->getKey())->where('status', CohortMember::WAITLISTED)->min('queued_at');
        $member ??= new CohortMember(['cohort_id' => $cohort->getKey(), 'learner_id' => $hold->learner_id]);
        $member->fill([
            'status' => CohortMember::WAITLISTED, 'queued_at' => $first === null ? now() : Carbon::parse((string) $first)->subSecond(),
            'seated_at' => null, 'removed_at' => null,
        ])->save();
    }

    /** @param  array<string, mixed>  $after */
    private function record(string $event, Cohort $cohort, ?int $actorId, array $after): void
    {
        if ($this->audit->record($event, $cohort, $actorId, null, $after) === null) {
            throw new RuntimeException('Unable to record cohort audit.');
        }
    }
}
