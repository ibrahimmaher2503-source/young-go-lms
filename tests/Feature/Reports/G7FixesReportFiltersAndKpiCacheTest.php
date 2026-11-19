<?php

declare(strict_types=1);

// T-G7-FIXES-C item 1 and T-G7-FIXES-A D1 | market-local report range defaults, timezone name and the dashboard KPI cache payload

use App\Domain\Reports\Dashboards\DashboardKpi;
use App\Domain\Reports\ReportFilters;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('defaults the report range to the 30 market-local days ending today, Cairo time, at 23:25 UTC', function (): void {
    // 18 Nov 23:25 UTC is 19 Nov 01:25 in Egypt, so the market day is already the 19th.
    Carbon::setTestNow(Carbon::parse('2026-11-18 23:25:00', 'UTC'));

    expect(ReportFilters::defaultTo())->toBe('2026-11-19')
        ->and(ReportFilters::defaultFrom())->toBe('2026-10-21');
});

it('keeps the range inputs as the market-local caption dates after parsing', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-18 23:25:00', 'UTC'));
    $filters = ReportFilters::fromInput(['from' => ReportFilters::defaultFrom(), 'to' => ReportFilters::defaultTo()]);

    expect($filters->localFromDate())->toBe('2026-10-21')
        ->and($filters->localToDate())->toBe('2026-11-19')
        ->and($filters->timezoneName())->toBe('Africa/Cairo');
});

it('uses the Cairo timezone name for the report range when no market is set', function (): void {
    $filters = ReportFilters::fromInput(['from' => '2026-11-01', 'to' => '2026-11-05']);

    expect($filters->timezoneName())->toBe('Africa/Cairo');
});

it('rebuilds a dashboard KPI from its plain-array cache form with the same values', function (): void {
    $count = DashboardKpi::count('open_orders', 7, 'heroicon-o-shopping-bag', 'warning', 2);
    $money = DashboardKpi::money('revenue', ['EGP' => 150000, 'SAR' => 2500], 'heroicon-o-banknotes');
    $percent = DashboardKpi::percent('completion', 42.5, 'heroicon-o-chart-pie');

    foreach ([$count, $money, $percent] as $kpi) {
        // The cache stores arrays only, so the plain form must survive a round trip unchanged.
        expect(DashboardKpi::fromArray($kpi->toArray()))->toEqual($kpi);
    }

    expect(DashboardKpi::fromArray($money->toArray())->money)->toBe(['EGP' => 150000, 'SAR' => 2500]);
});

it('treats a cache payload of the wrong shape as a miss by returning null', function (mixed $payload): void {
    expect(DashboardKpi::fromArray($payload))->toBeNull();
})->with([
    'null' => [null],
    'string' => ['x'],
    'empty array' => [[]],
    'object list' => [[new stdClass]],
    'non-int money' => [['key' => 'revenue', 'kind' => 'money', 'value' => 1, 'money' => ['EGP' => '1.5'], 'secondary' => null, 'icon' => 'i', 'tone' => 't']],
]);
