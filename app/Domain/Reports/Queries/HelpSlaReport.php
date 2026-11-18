<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Reports\QueryTools;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Help-request backlog and response SLA per course (AI spec 12). Requests created in the range. It counts and times only: the
 * question text, the AI answer, the messages and the student are never selected. Overdue = the escalation job marked it overdue.
 * Average first reply is in minutes over requests that were replied to.
 */
final class HelpSlaReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::HelpSla;
    }

    public function columns(): array
    {
        return ['course' => 'text', 'requests' => 'int', 'open' => 'int', 'overdue' => 'int', 'resolved' => 'int', 'replied' => 'int', 'avg_first_reply_minutes' => 'int'];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach ($this->query($filters)->get() as $row) {
            $replied = (int) $row->replied_count;

            yield [
                ReportLabels::translated((string) $row->course_title, '#'.$row->course_id), (int) $row->requests, (int) $row->open_count, (int) $row->overdue_count,
                (int) $row->resolved_count, $replied, $replied === 0 ? 0 : (int) round(((float) $row->reply_seconds) / $replied / 60),
            ];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return QueryTools::count($this->query($filters));
    }

    public function totals(ReportFilters $filters): array
    {
        return QueryTools::sums($this->rows($filters), $this->columns(), ['requests', 'open', 'overdue', 'resolved']);
    }

    private function query(ReportFilters $filters): Builder
    {
        $open = implode(',', array_map(static fn (string $status): string => "'{$status}'", HelpRequestStatus::openValues()));
        $seconds = QueryTools::secondsBetween('help_requests.created_at', 'help_requests.first_replied_at');

        return DB::table('help_requests')
            ->join('courses', 'courses.id', '=', 'help_requests.course_id')
            ->whereBetween('help_requests.created_at', [$filters->from, $filters->to])
            ->when($filters->courseIds !== null, static fn (Builder $query): Builder => $query->whereIn('help_requests.course_id', $filters->courseIds ?? []))
            ->groupBy('help_requests.course_id', 'courses.title')->orderBy('help_requests.course_id')
            ->selectRaw(
                'help_requests.course_id, courses.title as course_title, count(*) as requests, '
                ."sum(case when help_requests.status in ({$open}) then 1 else 0 end) as open_count, "
                .'sum(case when help_requests.overdue_at is not null then 1 else 0 end) as overdue_count, '
                ."sum(case when help_requests.status in ('resolved', 'closed') then 1 else 0 end) as resolved_count, "
                .'sum(case when help_requests.first_replied_at is not null then 1 else 0 end) as replied_count, '
                ."coalesce(sum(case when help_requests.first_replied_at is not null then {$seconds} else 0 end), 0) as reply_seconds",
            );
    }
}
