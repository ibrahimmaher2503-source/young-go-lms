<?php

declare(strict_types=1);

namespace App\Domain\Reports\Dashboards;

/** What one role dashboard shows: KPI tiles (report-backed, over the last N days) and live work queues. */
final readonly class DashboardData
{
    /**
     * @param  list<DashboardKpi>  $kpis
     * @param  list<DashboardKpi>  $queues
     */
    public function __construct(
        public array $kpis,
        public array $queues,
        public int $days,
    ) {}

    public function isEmpty(): bool
    {
        return $this->kpis === [] && $this->queues === [];
    }
}
