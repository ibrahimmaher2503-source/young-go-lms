<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\AI\Queries\UsageReport;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use App\Domain\Settings\AiSettings;

/**
 * AI usage per day: requests, failures and cost. It wraps `UsageReport` (the assistant usage page), so the two always agree. It reads
 * `ai_usage` only: no message, question, answer or student (not even the opaque hash). Only the date range applies.
 */
final class AiUsageReport implements ReportDefinition
{
    public function __construct(private readonly UsageReport $usage) {}

    public function key(): ReportKey
    {
        return ReportKey::AiUsage;
    }

    public function columns(): array
    {
        return ['day' => 'text', 'requests' => 'int', 'errors' => 'int', 'cost' => 'money'];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach ($this->usage->for($filters->from, $filters->to)['by_day'] as $day) {
            yield [$day['day'], $day['requests'], $day['errors'], ReportLabels::major($day['cost_minor'])];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return count($this->usage->for($filters->from, $filters->to)['by_day']);
    }

    public function totals(ReportFilters $filters): array
    {
        $totals = $this->usage->for($filters->from, $filters->to)['totals'];
        $currency = app(AiSettings::class)->budget_currency;

        return [
            ['key' => 'ai_requests', 'currency' => null, 'value' => $totals['requests'], 'money' => false],
            ['key' => 'ai_errors', 'currency' => null, 'value' => $totals['errors'], 'money' => false],
            ['key' => 'ai_cost', 'currency' => $currency, 'value' => ReportLabels::major($totals['cost_minor']), 'money' => true],
            ['key' => 'ai_avg_latency', 'currency' => null, 'value' => $totals['avg_latency_ms'], 'money' => false],
            ['key' => 'ai_p95_latency', 'currency' => null, 'value' => $totals['p95_latency_ms'], 'money' => false],
        ];
    }
}
