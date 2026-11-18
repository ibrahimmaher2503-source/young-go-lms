<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Reports\QueryTools;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Refunds. One row per EXECUTED refund with `executed_at` inside the range (requested, approved, rejected and failed refunds are
 * not money out and are not here). Market filters the order's market; course/instructor keep refunds of orders that contain a
 * line for those courses. The refund reason text and the people involved are not exported.
 */
final class RefundsReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::Refunds;
    }

    public function columns(): array
    {
        return ['reference' => 'text', 'order_reference' => 'text', 'executed_at' => 'datetime', 'market' => 'text', 'currency' => 'text', 'method' => 'text', 'gateway' => 'text', 'amount' => 'money'];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach ($this->query($filters)->orderBy('refunds.id')->lazyById(Config::integer('reports.chunk'), 'refunds.id', 'id') as $refund) {
            yield [
                (string) $refund->reference, (string) $refund->order_reference, ReportLabels::datetime((string) $refund->executed_at), (string) $refund->market,
                (string) $refund->currency, ReportLabels::status((string) $refund->method), (string) ($refund->gateway ?? ''), ReportLabels::major($refund->amount_minor),
            ];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function totals(ReportFilters $filters): array
    {
        $rows = DB::query()->fromSub($this->query($filters), 'refunds')->selectRaw('currency, count(*) as refunds, sum(amount_minor) as amount')->groupBy('currency')->orderBy('currency')->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[] = ['key' => 'refund_count', 'currency' => (string) $row->currency, 'value' => (int) $row->refunds, 'money' => false];
            $totals[] = ['key' => 'refund_amount', 'currency' => (string) $row->currency, 'value' => ReportLabels::major($row->amount), 'money' => true];
        }

        return $totals;
    }

    private function query(ReportFilters $filters): Builder
    {
        $query = DB::table('refunds')->join('orders', 'orders.id', '=', 'refunds.order_id')
            ->where('refunds.status', 'executed')
            ->whereBetween('refunds.executed_at', [$filters->from, $filters->to])
            ->when($filters->market !== null, static fn (Builder $query): Builder => $query->where('orders.market', $filters->market))
            ->select('refunds.id', 'refunds.reference', 'orders.reference as order_reference', 'refunds.executed_at', 'orders.market', 'refunds.currency', 'refunds.method', 'refunds.gateway', 'refunds.amount_minor');

        return QueryTools::ordersOfCourses($query, 'refunds.order_id', $filters->courseIds);
    }
}
