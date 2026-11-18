<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Reports\QueryTools;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use App\Domain\Shared\Markets\Market;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Instructor earnings and settlements, aggregated from the append-only ledger by instructor, course and currency over entries
 * with `earned_at` in the range. Earnings, reversals and adjustments are signed sums; net is their total. The status columns split
 * the same net by settlement state (accrued or reversed, in a settlement batch = pending + approved, held, paid), so they always add up to net.
 * Student identity is never selected. An instructor's own run is fixed to their ledger by `ReportCatalog::scope()` (`instructorId`);
 * the course filter here is the requested course (a ledger line without a course, such as an adjustment, has none). Market filters by
 * the market's currency.
 */
final class InstructorEarningsReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::InstructorEarnings;
    }

    public function columns(): array
    {
        return [
            'instructor' => 'text', 'course' => 'text', 'currency' => 'text', 'earnings' => 'money', 'reversals' => 'money', 'adjustments' => 'money', 'net' => 'money',
            'accrued' => 'money', 'in_settlement' => 'money', 'held' => 'money', 'paid' => 'money',
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach ($this->query($filters)->get() as $row) {
            yield [
                (string) $row->instructor_name, $row->course_id === null ? '' : ReportLabels::translated((string) $row->course_title, '#'.$row->course_id), (string) $row->currency,
                ReportLabels::major($row->earnings), ReportLabels::major($row->reversals), ReportLabels::major($row->adjustments), ReportLabels::major($row->net),
                ReportLabels::major($row->accrued), ReportLabels::major($row->in_settlement), ReportLabels::major($row->held), ReportLabels::major($row->paid),
            ];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return QueryTools::count($this->query($filters));
    }

    public function totals(ReportFilters $filters): array
    {
        $rows = DB::query()->fromSub($this->entries($filters)->select('instructor_ledger_entries.currency', 'instructor_ledger_entries.type', 'instructor_ledger_entries.amount_minor'), 'ledger')
            ->selectRaw('currency, type, sum(amount_minor) as total')->groupBy('currency', 'type')->orderBy('currency')->get();

        $byCurrency = [];
        foreach ($rows as $row) {
            $currency = (string) $row->currency;
            $byCurrency[$currency] ??= ['earnings' => 0, 'reversals' => 0, 'adjustments' => 0];
            $key = match ((string) $row->type) {
                'earning' => 'earnings',
                'reversal' => 'reversals',
                default => 'adjustments',
            };
            $byCurrency[$currency][$key] += (int) $row->total;
        }

        $totals = [];
        foreach ($byCurrency as $currency => $sums) {
            foreach ($sums as $key => $value) {
                $totals[] = ['key' => $key, 'currency' => $currency, 'value' => ReportLabels::major($value), 'money' => true];
            }
            $totals[] = ['key' => 'net', 'currency' => $currency, 'value' => ReportLabels::major(array_sum($sums)), 'money' => true];
        }

        return $totals;
    }

    private function entries(ReportFilters $filters): Builder
    {
        return DB::table('instructor_ledger_entries')
            ->whereBetween('instructor_ledger_entries.earned_at', [$filters->from, $filters->to])
            ->when($filters->instructorId !== null, static fn (Builder $query): Builder => $query->where('instructor_ledger_entries.instructor_id', $filters->instructorId))
            ->when($filters->courseId !== null, static fn (Builder $query): Builder => $query->where('instructor_ledger_entries.course_id', $filters->courseId))
            ->when($filters->market !== null, static fn (Builder $query): Builder => $query->where('instructor_ledger_entries.currency', Market::from((string) $filters->market)->currency()));
    }

    private function query(ReportFilters $filters): Builder
    {
        $sum = static fn (string $condition, array $bindings = []): array => ["sum(case when {$condition} then instructor_ledger_entries.amount_minor else 0 end)", $bindings];
        [$earnings] = $sum("instructor_ledger_entries.type = 'earning'");
        [$reversals] = $sum("instructor_ledger_entries.type = 'reversal'");
        [$adjustments] = $sum("instructor_ledger_entries.type = 'adjustment'");
        [$accrued] = $sum("instructor_ledger_entries.status not in ('pending', 'approved', 'held', 'paid')");
        [$settling] = $sum("instructor_ledger_entries.status in ('pending', 'approved')");
        [$held] = $sum("instructor_ledger_entries.status = 'held'");
        [$paid] = $sum("instructor_ledger_entries.status = 'paid'");

        return $this->entries($filters)
            ->join('users', 'users.id', '=', 'instructor_ledger_entries.instructor_id')
            ->leftJoin('courses', 'courses.id', '=', 'instructor_ledger_entries.course_id')
            ->groupBy('instructor_ledger_entries.instructor_id', 'users.name', 'instructor_ledger_entries.course_id', 'courses.title', 'instructor_ledger_entries.currency')
            ->orderBy('users.name')->orderBy('instructor_ledger_entries.instructor_id')->orderBy('instructor_ledger_entries.course_id')->orderBy('instructor_ledger_entries.currency')
            ->selectRaw(
                "users.name as instructor_name, instructor_ledger_entries.course_id, courses.title as course_title, instructor_ledger_entries.currency, {$earnings} as earnings, {$reversals} as reversals, {$adjustments} as adjustments, "
                .'sum(instructor_ledger_entries.amount_minor) as net, '
                ."{$accrued} as accrued, {$settling} as in_settlement, {$held} as held, {$paid} as paid",
            );
    }
}
