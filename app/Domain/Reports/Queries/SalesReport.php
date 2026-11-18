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
 * Sales and revenue. One row per order that was paid (`paid_at` set) with `paid_at` inside the range. The sale is the order total;
 * `refunded` is the sum of that order's EXECUTED refunds (any date), so net = total - refunded and the totals are the sums of the
 * rows, per currency (currencies are never added together). A course or instructor filter keeps the orders that contain a line
 * for those courses (a bundle counts through its allocations); the row amounts are always the whole order. No payer identity.
 */
final class SalesReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::Sales;
    }

    public function columns(): array
    {
        return [
            'reference' => 'text', 'paid_at' => 'datetime', 'market' => 'text', 'currency' => 'text', 'status' => 'text',
            'subtotal' => 'money', 'discount' => 'money', 'total' => 'money', 'refunded' => 'money', 'net' => 'money',
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach ($this->query($filters)->orderBy('orders.id')->lazyById(Config::integer('reports.chunk'), 'orders.id', 'id') as $order) {
            $total = (int) $order->total_minor;
            $refunded = (int) $order->refunded_minor;

            yield [
                (string) $order->reference, ReportLabels::datetime((string) $order->paid_at), (string) $order->market, (string) $order->currency, ReportLabels::status((string) $order->status),
                ReportLabels::major($order->subtotal_minor), ReportLabels::major($order->discount_minor), ReportLabels::major($total), ReportLabels::major($refunded), ReportLabels::major($total - $refunded),
            ];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function totals(ReportFilters $filters): array
    {
        $rows = DB::query()->fromSub($this->query($filters), 'sales')
            ->selectRaw('currency, count(*) as orders, sum(total_minor) as gross, sum(refunded_minor) as refunded')
            ->groupBy('currency')->orderBy('currency')->get();

        $totals = [];
        foreach ($rows as $row) {
            $currency = (string) $row->currency;
            $gross = (int) $row->gross;
            $refunded = (int) $row->refunded;
            $totals[] = ['key' => 'orders', 'currency' => $currency, 'value' => (int) $row->orders, 'money' => false];
            $totals[] = ['key' => 'gross', 'currency' => $currency, 'value' => ReportLabels::major($gross), 'money' => true];
            $totals[] = ['key' => 'refunded', 'currency' => $currency, 'value' => ReportLabels::major($refunded), 'money' => true];
            $totals[] = ['key' => 'net', 'currency' => $currency, 'value' => ReportLabels::major($gross - $refunded), 'money' => true];
        }

        return $totals;
    }

    private function query(ReportFilters $filters): Builder
    {
        $orders = DB::table('orders')
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$filters->from, $filters->to])
            ->when($filters->market !== null, static fn (Builder $query): Builder => $query->where('orders.market', $filters->market))
            ->select('orders.id', 'orders.reference', 'orders.paid_at', 'orders.market', 'orders.currency', 'orders.status', 'orders.subtotal_minor', 'orders.discount_minor', 'orders.total_minor')
            ->selectSub(
                DB::table('refunds')->selectRaw('coalesce(sum(refunds.amount_minor), 0)')->whereColumn('refunds.order_id', 'orders.id')->where('refunds.status', 'executed'),
                'refunded_minor',
            );

        return QueryTools::ordersOfCourses($orders, 'orders.id', $filters->courseIds);
    }
}
