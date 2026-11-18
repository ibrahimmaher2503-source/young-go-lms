<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundStatus;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerEntryType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reverses instructor earnings in proportion to an executed refund (T-P8-09). Reversals are cumulative so partial refunds never
 * drift: for each earning of the order, target(c) = round_half_up(earning x c / order total) where c is the amount refunded so far
 * (this refund and every executed refund of the order with a lower id); this refund writes target(c) - target(c before it) as a
 * negative `reversal` entry, key `refund:<refund>:entry:<earning>`. A full refund therefore reverses exactly 100% and nothing is
 * ever over-reversed. Zero-amount earnings and zero deltas write nothing. Idempotent; a refund that is not executed writes nothing.
 * Plan and bundle earnings are reversed the same way (a refund names no line). Audited once per call that wrote entries.
 */
final class ReverseEarningsForRefund
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return int number of reversal entries written */
    public function __invoke(Refund $refund): int
    {
        return DB::transaction(function () use ($refund): int {
            $fresh = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());
            if ($fresh->status !== RefundStatus::Executed) {
                return 0;
            }
            $order = Order::query()->findOrFail($fresh->order_id);
            $total = $order->total_minor;
            if ($total <= 0) {
                return 0;
            }

            $upTo = min($total, (int) Refund::query()->where('order_id', $order->getKey())->where('status', RefundStatus::Executed->value)->where('id', '<=', $fresh->getKey())->sum('amount_minor'));
            $before = max(0, $upTo - $fresh->amount_minor);

            $written = 0;
            $earnings = LedgerEntry::query()->where('order_id', $order->getKey())->where('type', LedgerEntryType::Earning->value)->where('amount_minor', '>', 0)->orderBy('id')->get();
            foreach ($earnings as $earning) {
                $delta = $this->target($earning->amount_minor, $upTo, $total) - $this->target($earning->amount_minor, $before, $total);
                $key = 'refund:'.$fresh->getKey().':entry:'.$earning->getKey();
                if ($delta <= 0 || LedgerEntry::query()->where('source_key', $key)->exists()) {
                    continue;
                }

                try {
                    DB::transaction(static fn () => LedgerEntry::query()->create([
                        'instructor_id' => $earning->instructor_id, 'course_id' => $earning->course_id, 'type' => LedgerEntryType::Reversal->value,
                        'sale_type' => $earning->sale_type?->value, 'source_key' => $key, 'order_id' => $order->getKey(), 'order_item_id' => $earning->order_item_id,
                        'allocation_id' => $earning->allocation_id, 'subscription_period_id' => $earning->subscription_period_id, 'refund_id' => $fresh->getKey(),
                        'reverses_entry_id' => $earning->getKey(), 'student_id' => $earning->student_id, 'rule_id' => $earning->rule_id, 'rule_source' => $earning->rule_source,
                        'currency' => $earning->currency, 'amount_minor' => -$delta, 'earned_at' => $fresh->executed_at ?? now(),
                    ]));
                    $written++;
                } catch (UniqueConstraintViolationException) {
                    continue; // a concurrent call wrote it first
                }
            }

            if ($written > 0 && $this->audit->record('ledger.earnings_reversed', $fresh, null, null, ['refund' => $fresh->reference, 'order' => $order->reference, 'entries' => $written]) === null) {
                throw new RuntimeException('Unable to record ledger audit.');
            }

            return $written;
        });
    }

    /** round_half_up(amount x refunded / total) in integer math. */
    private function target(int $amount, int $refunded, int $total): int
    {
        return intdiv($amount * $refunded * 2 + $total, 2 * $total);
    }
}
