<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\InstructorFinance\InstructorStatement;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerEntryType;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\InstructorFinance\SettlementNotices;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Shared\Markets\Market;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Freezes one instructor's statement for one CLOSED month and currency (T-P8-10). Totals come from the ledger entries earned in
 * the month; `balance_forward` is what earlier entries still leave unpaid (a negative value is a deduction carried forward) and
 * `closing = balance_forward + net - settled`. Idempotent: the same instructor, currency and month always returns the first
 * statement. Needs `settlement.manage`; `system()` is for the monthly command only.
 */
final class GenerateInstructorStatement
{
    private const array UNSETTLED = [LedgerStatus::Accrued, LedgerStatus::Pending, LedgerStatus::Approved, LedgerStatus::Held];

    public function __construct(private readonly AuditLogger $audit, private readonly SettlementNotices $notices) {}

    public function __invoke(User $actor, int $instructorId, string $period, string $currency): InstructorStatement
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('settlement.manage')) {
            throw new AuthorizationException;
        }

        return $this->generate((int) $actor->getKey(), $instructorId, $period, $currency);
    }

    /** Scheduler entry point: no actor, audited as a system action. */
    public function system(int $instructorId, string $period, string $currency): InstructorStatement
    {
        return $this->generate(null, $instructorId, $period, $currency);
    }

    private function generate(?int $actorId, int $instructorId, string $period, string $currency): InstructorStatement
    {
        $currency = strtoupper(trim($currency));
        $start = $this->start($period);
        $end = $start->copy()->endOfMonth();

        $existing = $this->find($instructorId, $currency, $period);
        if ($existing !== null) {
            return $existing;
        }

        $instructor = User::query()->find($instructorId);
        if (! $instructor instanceof User || ! $instructor->hasAnyRole(PublicInstructors::ROLES)) {
            $this->refuse('instructor_id', 'instructor_invalid');
        }
        if (! in_array($currency, array_map(static fn (Market $m): string => $m->currency(), Market::cases()), true)) {
            $this->refuse('currency', 'currency_invalid');
        }
        if ($end->greaterThanOrEqualTo(now())) {
            $this->refuse('period', 'period_open');
        }

        $entries = LedgerEntry::query()->with(['course', 'order'])->where('instructor_id', $instructorId)->where('currency', $currency)
            ->whereBetween('earned_at', [$start, $end])->orderBy('earned_at')->orderBy('id')->get();
        if ($entries->isEmpty()) {
            $this->refuse('period', 'statement_empty');
        }

        $sum = static fn (LedgerEntryType $type): int => (int) $entries->where('type', $type)->sum('amount_minor');
        $net = (int) $entries->sum('amount_minor');
        $settled = (int) $entries->where('status', LedgerStatus::Paid)->sum('amount_minor');
        $forward = (int) LedgerEntry::query()->where('instructor_id', $instructorId)->where('currency', $currency)
            ->where('earned_at', '<', $start)->whereIn('status', array_map(static fn (LedgerStatus $s): string => $s->value, self::UNSETTLED))->sum('amount_minor');

        $lines = $entries->map(static fn (LedgerEntry $e): array => [
            'id' => $e->getKey(), 'date' => $e->earned_at->toDateString(),
            'course_ar' => $e->course === null ? null : $e->course->getTranslation('title', 'ar', false),
            'course_en' => $e->course === null ? null : $e->course->getTranslation('title', 'en', false),
            'type' => $e->type->value, 'sale_type' => $e->sale_type?->value, 'order' => $e->order?->reference,
            'amount_minor' => $e->amount_minor, 'status' => $e->status->value,
        ])->values()->all();

        $number = 'STM-'.str_replace('-', '', $period).'-'.str_pad((string) $instructorId, 5, '0', STR_PAD_LEFT).'-'.$currency;

        try {
            $statement = DB::transaction(function () use ($actorId, $instructorId, $period, $currency, $start, $end, $entries, $sum, $net, $settled, $forward, $lines, $number): InstructorStatement {
                $statement = InstructorStatement::query()->create([
                    'number' => $number, 'instructor_id' => $instructorId, 'currency' => $currency, 'period' => $period,
                    'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'entry_count' => $entries->count(),
                    'earnings_minor' => $sum(LedgerEntryType::Earning), 'reversals_minor' => $sum(LedgerEntryType::Reversal), 'adjustments_minor' => $sum(LedgerEntryType::Adjustment),
                    'net_minor' => $net, 'settled_minor' => $settled, 'balance_forward_minor' => $forward, 'closing_balance_minor' => $forward + $net - $settled,
                    'snapshot' => ['lines' => $lines], 'generated_by' => $actorId, 'generated_at' => now(),
                ]);

                $recorded = $this->audit->record('statement.generated', $statement, $actorId, null, [
                    'instructor_id' => $instructorId, 'period' => $period, 'currency' => $currency, 'net_minor' => $net, 'closing_balance_minor' => $statement->closing_balance_minor,
                ], ['system' => $actorId === null]);
                if ($recorded === null) {
                    throw new RuntimeException('Unable to record statement audit.');
                }

                return $statement;
            });
        } catch (UniqueConstraintViolationException) {
            return InstructorStatement::query()->where('instructor_id', $instructorId)->where('currency', $currency)->where('period', $period)->firstOrFail();
        }

        $this->notices->statementReady($statement);

        return $statement;
    }

    private function find(int $instructorId, string $currency, string $period): ?InstructorStatement
    {
        return InstructorStatement::query()->where('instructor_id', $instructorId)->where('currency', $currency)->where('period', $period)->first();
    }

    private function start(string $period): Carbon
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            $this->refuse('period', 'period_invalid');
        }

        return Carbon::createFromFormat('!Y-m', $period) ?? throw ValidationException::withMessages(['period' => __('settlements.period_invalid')]);
    }

    private function refuse(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('settlements.'.$key)]);
    }
}
