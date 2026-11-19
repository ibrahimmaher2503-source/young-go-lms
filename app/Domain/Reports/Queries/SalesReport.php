<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Commerce\PaymentAttemptStatus;
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
            'method' => 'text', 'manual_approved_at' => 'datetime', 'coupon_code' => 'text',
            'recorded_collections' => 'money', 'collection_currency' => 'text',
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $orders = $this->withCollections($this->query($filters))
            ->selectSub(DB::table('coupon_redemptions')->join('coupons', 'coupons.id', '=', 'coupon_redemptions.coupon_id')
                ->select('coupons.code')->whereColumn('coupon_redemptions.order_id', 'orders.id')
                ->where('coupon_redemptions.status', CouponRedemptionStatus::Redeemed->value), 'coupon_code');
        foreach ($orders->lazyById(Config::integer('reports.chunk'), 'orders.id', 'id') as $order) {
            $total = (int) $order->total_minor;
            $refunded = (int) $order->refunded_minor;

            yield [
                (string) $order->reference, ReportLabels::datetime((string) $order->paid_at), (string) $order->market, (string) $order->currency, ReportLabels::status((string) $order->status),
                ReportLabels::major($order->subtotal_minor), ReportLabels::major($order->discount_minor), ReportLabels::major($total), ReportLabels::major($refunded), ReportLabels::major($total - $refunded),
                ReportLabels::status($order->proof_currency === null ? $order->gateway : 'instapay_transfer'),
                ReportLabels::datetime($order->manual_approved_at), (string) $order->coupon_code,
                $order->proof_currency === null && $order->gateway === null ? null : ReportLabels::major($order->proof_minor ?? $total),
                $order->proof_currency ?? ($order->gateway === null ? null : $order->currency),
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

        $sources = DB::query()->fromSub($this->withCollections($this->query($filters)), 'collection_sources')
            ->selectRaw('coalesce(proof_currency, currency) as received_currency, coalesce(proof_minor, total_minor) as received_minor')
            ->where(static fn (Builder $query) => $query->whereNotNull('proof_currency')->orWhereNotNull('gateway'));
        $collections = DB::query()->fromSub($sources, 'collections')
            ->selectRaw('received_currency, sum(received_minor) as received_minor')->groupBy('received_currency')->orderBy('received_currency')->get();
        foreach ($collections as $row) {
            $totals[] = ['key' => 'recorded_collections', 'currency' => (string) $row->received_currency, 'value' => ReportLabels::major($row->received_minor), 'money' => true];
        }

        return $totals;
    }

    private function withCollections(Builder $orders): Builder
    {
        $proof = DB::table('manual_payment_proofs')->whereColumn('order_id', 'orders.id')
            ->where('status', ManualPaymentStatus::Approved->value)->orderBy('id')->limit(1);

        return $orders
            ->selectSub(DB::table('payment_attempts')->select('gateway')->whereColumn('order_id', 'orders.id')
                ->where('status', PaymentAttemptStatus::Succeeded->value)->orderBy('sequence')->limit(1), 'gateway')
            ->selectSub($proof->clone()->select('reviewed_at'), 'manual_approved_at')
            ->selectSub($proof->clone()->select('amount_minor'), 'proof_minor')
            ->selectSub($proof->select('currency'), 'proof_currency');
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
