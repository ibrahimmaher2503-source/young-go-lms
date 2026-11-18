<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Actions\AllocateBundlePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\OrderItemAllocation;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\OrderItemAllocations;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerEntryType;
use App\Domain\InstructorFinance\Queries\CommissionRules;
use App\Domain\InstructorFinance\SaleType;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Domain\Subscriptions\SubscriptionPlanCourse;
use App\Models\Course;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes the instructor earnings of one paid order (T-P8-09, PRD 17.4). Safe to call again for the same order: every line has a
 * unique source key, so a replayed webhook, a double approval or the backfill command adds nothing.
 *  - Course line: one entry, `order_item:<id>:course:<course>`, percentage of the line's net share (OrderItemAllocations).
 *  - Bundle line: one entry per course allocation, same key shape, percentage of that course's allocated share, written even when
 *    the learner already held the course (earnings follow the sale, not the access).
 *  - Plan line: one entry per plan course (O-010: the period's net price split equally across the courses, rate on each share; a fixed-amount rule still wins), `subscription_period:<period>:course:<course>`; the period already
 *    belongs to one student, so this is the once-per-student-course-period guard. No watch time or engagement is read.
 * The rule is looked up as of the payment date (CommissionRules). The beneficiary is the course owner (`owner_instructor_id`);
 * an academy course without one earns nothing. With no rule the entry is written at amount 0 so uncovered sales are visible.
 * The commissionable base is the net line share, after discount and excluding VAT (O-011); gross and discount are stored beside it. An order that was never
 * paid writes nothing. Audited once per call that wrote entries (`ledger.earnings_recorded`).
 */
final class RecordOrderEarnings
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CommissionRules $rules,
        private readonly OrderItemAllocations $allocations,
        private readonly AllocateBundlePrice $split,
    ) {}

    /** @return int number of entries written */
    public function __invoke(Order $order): int
    {
        return DB::transaction(function () use ($order): int {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($locked->paid_at === null) {
                return 0;
            }

            $written = 0;
            foreach ($locked->items()->orderBy('id')->get() as $item) {
                $written += $item->product_type === ProductType::Plan ? $this->plan($locked, $item) : $this->sale($locked, $item);
            }

            if ($written > 0 && $this->audit->record('ledger.earnings_recorded', $locked, null, null, ['order' => $locked->reference, 'entries' => $written]) === null) {
                throw new RuntimeException('Unable to record ledger audit.');
            }

            return $written;
        });
    }

    private function sale(Order $order, OrderItem $item): int
    {
        $type = SaleType::fromProduct($item->product_type);
        $rows = $item->product_type === ProductType::Bundle
            ? OrderItemAllocation::query()->where('order_item_id', $item->getKey())->get()->keyBy('course_id')
            : collect();

        $written = 0;
        foreach ($this->allocations->for($item) as $courseId => $base) {
            $allocation = $rows->get($courseId);
            $gross = $allocation instanceof OrderItemAllocation ? $allocation->allocated_minor : $item->unit_price_minor;

            $written += $this->write($order, $item, (int) $courseId, $type, 'order_item:'.$item->getKey().':course:'.$courseId, [
                'allocation_id' => $allocation?->getKey(), 'gross_minor' => $gross, 'discount_minor' => max(0, $gross - $base), 'base_minor' => $base,
            ], $base);
        }

        return $written;
    }

    private function plan(Order $order, OrderItem $item): int
    {
        $period = SubscriptionPeriod::query()->where('order_item_id', $item->getKey())->first();
        if ($period === null) {
            return 0;
        }

        // O-010: the period's net price is split equally across the plan's courses; each course earns the rate on its share.
        $courseIds = SubscriptionPlanCourse::query()->where('plan_id', $item->product_id)->orderBy('id')->pluck('course_id')->map(static fn ($id): int => (int) $id)->all();
        if ($courseIds === []) {
            return 0;
        }
        $net = max(0, $item->allocated_minor ?? ($item->unit_price_minor - $item->discount_minor));
        $weights = array_fill_keys($courseIds, 1);
        $shares = $this->split->split($net, $weights);
        $grossShares = $this->split->split(max(0, $item->unit_price_minor), $weights);

        $written = 0;
        foreach ($courseIds as $courseId) {
            $share = $shares[$courseId];
            $written += $this->write($order, $item, $courseId, SaleType::Subscription, 'subscription_period:'.$period->getKey().':course:'.$courseId, [
                'subscription_period_id' => $period->getKey(), 'gross_minor' => $grossShares[$courseId], 'discount_minor' => max(0, $grossShares[$courseId] - $share), 'base_minor' => $share,
            ], $share);
        }

        return $written;
    }

    /**
     * @param  array<string, int|null>  $extra
     */
    private function write(Order $order, OrderItem $item, int $courseId, SaleType $type, string $key, array $extra, int $base): int
    {
        $instructorId = Course::query()->whereKey($courseId)->value('owner_instructor_id');
        if ($instructorId === null || LedgerEntry::query()->where('source_key', $key)->exists()) {
            return 0;
        }

        $paidAt = $order->paid_at ?? now();
        $rate = $this->rules->resolve((int) $instructorId, $courseId, $type, $order->currency, $paidAt);

        try {
            DB::transaction(static fn () => LedgerEntry::query()->create($extra + [
                'instructor_id' => (int) $instructorId, 'course_id' => $courseId, 'type' => LedgerEntryType::Earning->value, 'sale_type' => $type->value,
                'source_key' => $key, 'order_id' => $order->getKey(), 'order_item_id' => $item->getKey(), 'student_id' => $item->learner_id,
                'rule_id' => $rate->rule?->getKey(), 'rule_source' => $rate->source, 'currency' => $order->currency,
                'rate_bps' => $rate->rateBps, 'fixed_amount_minor' => $rate->fixedAmountMinor, 'amount_minor' => $rate->amountFor($base), 'earned_at' => $paidAt,
            ]));
        } catch (UniqueConstraintViolationException) {
            return 0; // a concurrent call wrote it first
        }

        return 1;
    }
}
