<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Payment reconciliation differences found in the range (`created_at` of the mismatch), with no cap (the finance page lists the
 * latest 100). Market filters the market of the matched order; a mismatch with no order has no market and is kept only when no
 * market is chosen. Difference = gateway amount - our amount.
 */
final class ReconciliationMismatchReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::Reconciliation;
    }

    public function columns(): array
    {
        return [
            'found_at' => 'datetime', 'type' => 'text', 'reason' => 'text', 'status' => 'text', 'gateway' => 'text', 'attempt_reference' => 'text', 'order_reference' => 'text',
            'currency' => 'text', 'gateway_amount' => 'money', 'local_amount' => 'money', 'difference' => 'money',
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach ($this->query($filters)->orderBy('payment_reconciliation_mismatches.id')->lazyById(Config::integer('reports.chunk'), 'payment_reconciliation_mismatches.id', 'id') as $row) {
            $gateway = $row->gateway_amount_minor === null ? null : (int) $row->gateway_amount_minor;
            $local = $row->local_amount_minor === null ? null : (int) $row->local_amount_minor;

            yield [
                ReportLabels::datetime((string) $row->created_at), (string) __('payment_reconciliation.types.'.$row->type), (string) __('payment_reconciliation.reasons.'.$row->reason),
                ReportLabels::status((string) $row->status), (string) $row->gateway, (string) ($row->attempt_reference ?? ''), (string) ($row->order_reference ?? ''), (string) ($row->currency ?? ''),
                $gateway === null ? '' : ReportLabels::major($gateway), $local === null ? '' : ReportLabels::major($local), ReportLabels::major(($gateway ?? 0) - ($local ?? 0)),
            ];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function totals(ReportFilters $filters): array
    {
        $open = $this->query($filters)->where('payment_reconciliation_mismatches.status', 'open')->count();

        return [
            ['key' => 'mismatches', 'currency' => null, 'value' => $this->count($filters), 'money' => false],
            ['key' => 'open_mismatches', 'currency' => null, 'value' => $open, 'money' => false],
        ];
    }

    private function query(ReportFilters $filters): Builder
    {
        return DB::table('payment_reconciliation_mismatches')
            ->leftJoin('orders', 'orders.id', '=', 'payment_reconciliation_mismatches.order_id')
            ->whereBetween('payment_reconciliation_mismatches.created_at', [$filters->from, $filters->to])
            ->when($filters->market !== null, static fn (Builder $query): Builder => $query->where('orders.market', $filters->market))
            ->select(
                'payment_reconciliation_mismatches.id', 'payment_reconciliation_mismatches.created_at', 'payment_reconciliation_mismatches.type', 'payment_reconciliation_mismatches.reason',
                'payment_reconciliation_mismatches.status', 'payment_reconciliation_mismatches.gateway', 'payment_reconciliation_mismatches.attempt_reference', 'orders.reference as order_reference',
                'payment_reconciliation_mismatches.currency', 'payment_reconciliation_mismatches.gateway_amount_minor', 'payment_reconciliation_mismatches.local_amount_minor',
            );
    }
}
