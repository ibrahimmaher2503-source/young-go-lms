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
 * Live-session attendance, one row per (not archived) session that starts in the range, with counts only (no learner is named).
 * Expected = seated cohort members, or the marked learners for a session without a cohort. Rate = (present + late) / (expected - excused).
 */
final class CohortAttendanceReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::CohortAttendance;
    }

    public function columns(): array
    {
        return [
            'starts_at' => 'datetime', 'course' => 'text', 'session' => 'text', 'cohort' => 'text', 'status' => 'text', 'seated' => 'int',
            'present' => 'int', 'late' => 'int', 'absent' => 'int', 'excused' => 'int', 'attendance_rate' => 'percent',
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach ($this->query($filters)->orderBy('live_sessions.id')->lazyById(Config::integer('reports.chunk'), 'live_sessions.id', 'id') as $session) {
            $present = (int) $session->present_count;
            $late = (int) $session->late_count;
            $absent = (int) $session->absent_count;
            $excused = (int) $session->excused_count;
            $seated = (int) $session->seated_count;
            $expected = ($seated > 0 ? $seated : $present + $late + $absent + $excused) - $excused;

            yield [
                ReportLabels::datetime((string) $session->starts_at), ReportLabels::translated((string) $session->course_title, '#'.$session->course_id),
                ReportLabels::translated((string) $session->title), $this->cohort($session), ReportLabels::status((string) $session->status), $seated,
                $present, $late, $absent, $excused, $expected > 0 ? round(($present + $late) * 100 / $expected, 1) : 0.0,
            ];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function totals(ReportFilters $filters): array
    {
        $totals = QueryTools::sums($this->rows($filters), $this->columns(), ['present', 'late', 'absent', 'excused']);
        array_unshift($totals, ['key' => 'sessions', 'currency' => null, 'value' => $this->count($filters), 'money' => false]);

        return $totals;
    }

    private function cohort(object $session): string
    {
        if ($session->cohort_name === null) {
            return '';
        }

        $translations = json_decode((string) $session->cohort_translations, true);
        $locale = app()->getLocale();

        return is_array($translations) && is_string($translations[$locale] ?? null) && $translations[$locale] !== '' ? $translations[$locale] : (string) $session->cohort_name;
    }

    private function query(ReportFilters $filters): Builder
    {
        $count = static fn (string $status): Builder => DB::table('live_attendance')->selectRaw('count(*)')
            ->whereColumn('live_attendance.live_session_id', 'live_sessions.id')->where('live_attendance.status', $status);

        return DB::table('live_sessions')
            ->join('courses', 'courses.id', '=', 'live_sessions.course_id')
            ->leftJoin('cohorts', 'cohorts.id', '=', 'live_sessions.cohort_id')
            ->whereNull('live_sessions.archived_at')
            ->whereBetween('live_sessions.starts_at', [$filters->from, $filters->to])
            ->when($filters->courseIds !== null, static fn (Builder $query): Builder => $query->whereIn('live_sessions.course_id', $filters->courseIds ?? []))
            ->select('live_sessions.id', 'live_sessions.course_id', 'live_sessions.title', 'live_sessions.starts_at', 'live_sessions.status', 'courses.title as course_title', 'cohorts.name as cohort_name', 'cohorts.name_translations as cohort_translations')
            ->selectSub(DB::table('cohort_members')->selectRaw('count(*)')->whereColumn('cohort_members.cohort_id', 'live_sessions.cohort_id')->where('cohort_members.status', 'seated'), 'seated_count')
            ->selectSub($count('present'), 'present_count')
            ->selectSub($count('late'), 'late_count')
            ->selectSub($count('absent'), 'absent_count')
            ->selectSub($count('excused'), 'excused_count');
    }
}
