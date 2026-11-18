<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Entitlements\EnrollmentReport;
use App\Domain\Reports\QueryTools;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Enrollments and completions, one row per course (aggregated, no learner is named). New = learners whose first access started in
 * the range; total = learners who ever had access; completions come from `course_completions` (one per learner and course, so a
 * retry never counts twice). Rate = all completions / all enrolled learners.
 */
final class EnrollmentsReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::Enrollments;
    }

    public function columns(): array
    {
        return [
            'course' => 'text', 'new_enrollments' => 'int', 'active_learners' => 'int', 'total_learners' => 'int',
            'new_completions' => 'int', 'total_completions' => 'int', 'completion_rate' => 'percent',
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $after = 0;
        $chunk = Config::integer('reports.chunk');
        do {
            $courses = $this->courses($filters)->where('courses.id', '>', $after)->orderBy('courses.id')->limit($chunk)->get();
            $ids = $courses->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $enrolled = EnrollmentReport::forCourses($ids, $filters->from, $filters->to);
            $completed = $this->completions($ids, $filters);

            foreach ($courses as $course) {
                $id = (int) $course->id;
                $enrollment = $enrolled[$id] ?? ['new' => 0, 'active' => 0, 'total' => 0];
                $done = $completed[$id] ?? ['new' => 0, 'total' => 0];

                yield [
                    ReportLabels::translated((string) $course->title, '#'.$id), $enrollment['new'], $enrollment['active'], $enrollment['total'],
                    $done['new'], $done['total'], $enrollment['total'] === 0 ? 0.0 : round($done['total'] * 100 / $enrollment['total'], 1),
                ];
                $after = $id;
            }
        } while ($courses->count() === $chunk);
    }

    public function count(ReportFilters $filters): int
    {
        return $this->courses($filters)->count();
    }

    public function totals(ReportFilters $filters): array
    {
        return QueryTools::sums($this->rows($filters), $this->columns(), ['new_enrollments', 'total_learners', 'new_completions', 'total_completions']);
    }

    private function courses(ReportFilters $filters): Builder
    {
        return DB::table('courses')->select('courses.id', 'courses.title')
            ->when($filters->courseIds !== null, static fn (Builder $query): Builder => $query->whereIn('courses.id', $filters->courseIds ?? []));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{new: int, total: int}>
     */
    private function completions(array $ids, ReportFilters $filters): array
    {
        if ($ids === []) {
            return [];
        }

        $out = [];
        $rows = DB::table('course_completions')->whereIn('course_id', $ids)->groupBy('course_id')
            ->selectRaw('course_id, count(*) as total, sum(case when completed_at >= ? and completed_at <= ? then 1 else 0 end) as new_count', [$filters->from->format('Y-m-d H:i:s'), $filters->to->format('Y-m-d H:i:s')])->get();
        foreach ($rows as $row) {
            $out[(int) $row->course_id] = ['new' => (int) $row->new_count, 'total' => (int) $row->total];
        }

        return $out;
    }
}
