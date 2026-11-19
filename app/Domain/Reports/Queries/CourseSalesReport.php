<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\OrderItemAllocations;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/** Paid course and bundle allocations after discounts and before refunds, grouped by course and currency. */
final class CourseSalesReport implements ReportDefinition
{
    public function __construct(private readonly OrderItemAllocations $allocations) {}

    public function key(): ReportKey
    {
        return ReportKey::CourseSales;
    }

    public function columns(): array
    {
        return ['course' => 'text', 'currency' => 'text', 'course_sales' => 'money'];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $sales = $this->sales($filters);
        $titles = DB::table('courses')->whereIn('id', array_keys($sales))->pluck('title', 'id');
        foreach ($sales as $courseId => $currencies) {
            foreach ($currencies as $currency => $minor) {
                yield [ReportLabels::translated($titles->get($courseId), '#'.$courseId), $currency, ReportLabels::major($minor)];
            }
        }
    }

    public function count(ReportFilters $filters): int
    {
        return array_sum(array_map('count', $this->sales($filters)));
    }

    public function totals(ReportFilters $filters): array
    {
        $totals = [];
        foreach ($this->sales($filters) as $currencies) {
            foreach ($currencies as $currency => $minor) {
                $totals[$currency] = ($totals[$currency] ?? 0) + $minor;
            }
        }
        ksort($totals);

        $rows = [];
        foreach ($totals as $currency => $minor) {
            $rows[] = ['key' => 'course_sales', 'currency' => $currency, 'value' => ReportLabels::major($minor), 'money' => true];
        }

        return $rows;
    }

    /** @return array<int, array<string, int>> */
    private function sales(ReportFilters $filters): array
    {
        $items = OrderItem::query()->with('allocations')->whereIn('product_type', [ProductType::Course, ProductType::Bundle])
            ->whereHas('order', static fn ($order) => $order->whereBetween('paid_at', [$filters->from, $filters->to])
                ->when($filters->market !== null, static fn ($query) => $query->where('market', $filters->market)));
        if ($filters->courseIds !== null) {
            $items->where(static function ($lines) use ($filters): void {
                $lines->where(static fn ($course) => $course->where('product_type', ProductType::Course)->whereIn('product_id', $filters->courseIds))
                    ->orWhereExists(static fn (Builder $allocations) => $allocations->from('order_item_allocations')->whereColumn('order_item_id', 'order_items.id')
                        ->whereIn('course_id', $filters->courseIds));
            });
        }

        $sales = [];
        foreach ($items->lazyById(Config::integer('reports.chunk')) as $item) {
            foreach ($this->allocations->for($item) as $courseId => $minor) {
                if ($filters->courseIds === null || in_array($courseId, $filters->courseIds, true)) {
                    $sales[$courseId][$item->currency] = ($sales[$courseId][$item->currency] ?? 0) + $minor;
                }
            }
        }
        ksort($sales);

        return $sales;
    }
}
