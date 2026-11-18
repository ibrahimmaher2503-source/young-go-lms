<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Query helpers the report definitions share. */
final class QueryTools
{
    /** Rows of a (possibly grouped) query. */
    public static function count(Builder $query): int
    {
        return DB::query()->fromSub($query->clone(), 'counted')->count();
    }

    /** Seconds between two timestamp columns, portable across the supported drivers. */
    public static function secondsBetween(string $from, string $to): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "(strftime('%s', {$to}) - strftime('%s', {$from}))",
            'pgsql' => "extract(epoch from ({$to} - {$from}))",
            default => "TIMESTAMPDIFF(SECOND, {$from}, {$to})",
        };
    }

    /**
     * Limits a query to orders (by the column that holds the order id) with a line for one of the courses: a single course line,
     * or a bundle allocation to it.
     *
     * @param  list<int>|null  $courseIds  null = no restriction
     */
    public static function ordersOfCourses(Builder $query, string $orderIdColumn, ?array $courseIds): Builder
    {
        if ($courseIds === null) {
            return $query;
        }

        return $query->whereExists(static function (Builder $lines) use ($courseIds, $orderIdColumn): void {
            $lines->from('order_items')->select(DB::raw('1'))->whereColumn('order_items.order_id', $orderIdColumn)
                ->where(static function (Builder $match) use ($courseIds): void {
                    $match->where(static fn (Builder $single): Builder => $single->where('order_items.product_type', 'course')->whereIn('order_items.product_id', $courseIds))
                        ->orWhereExists(static fn (Builder $allocation) => $allocation->from('order_item_allocations')->select(DB::raw('1'))
                            ->whereColumn('order_item_allocations.order_item_id', 'order_items.id')->whereIn('order_item_allocations.course_id', $courseIds));
                });
        });
    }

    /**
     * Sums the numeric columns of already-built rows into total tiles (for the small aggregated reports).
     *
     * @param  iterable<int, list<string|int|float|null>>  $rows
     * @param  array<string, string>  $columns
     * @param  list<string>  $sum  column keys to add up
     * @return list<array{key: string, currency: string|null, value: int|float, money: bool}>
     */
    public static function sums(iterable $rows, array $columns, array $sum): array
    {
        $index = array_flip(array_keys($columns));
        $totals = array_fill_keys($sum, 0);
        foreach ($rows as $row) {
            foreach ($sum as $key) {
                $totals[$key] += (float) ($row[$index[$key]] ?? 0);
            }
        }

        $out = [];
        foreach ($totals as $key => $value) {
            $money = ($columns[$key] ?? '') === 'money';
            $out[] = ['key' => $key, 'currency' => null, 'value' => $money ? round($value, 2) : (int) round($value), 'money' => $money];
        }

        return $out;
    }
}
