<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Engagement\ReviewStatus;
use App\Domain\Entitlements\EnrollmentReport;
use App\Domain\Reports\QueryTools;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use App\Models\SubmissionDecision;
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
            'average_progress' => 'percent', 'approved_reviews' => 'int', 'average_rating' => 'text',
            'attempts' => 'int', 'passed' => 'int',
            'graded_assignments' => 'int', 'accepted_assignments' => 'int', 'rejected_assignments' => 'int',
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $after = 0;
        $chunk = Config::integer('reports.chunk');
        do {
            $courses = $this->withPerformance($this->courses($filters), $filters)
                ->where('courses.id', '>', $after)->orderBy('courses.id')->limit($chunk)->get();
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
                    $course->average_progress === null ? null : round((float) $course->average_progress, 1),
                    (int) $course->approved_reviews, $course->average_rating === null ? null : round((float) $course->average_rating, 1),
                    (int) $course->attempts, (int) $course->passed,
                    (int) $course->graded_assignments, (int) $course->accepted_assignments, (int) $course->rejected_assignments,
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

    private function withPerformance(Builder $courses, ReportFilters $filters): Builder
    {
        $range = [$filters->from, $filters->to];
        $reviews = DB::table('course_reviews')->whereColumn('course_id', 'courses.id')
            ->where('status', ReviewStatus::Published->value)->whereBetween('created_at', $range);
        $attempts = DB::table('curriculum_items')->join('quiz_attempts', 'quiz_attempts.quiz_id', '=', 'curriculum_items.itemable_id')
            ->whereColumn('curriculum_items.course_id', 'courses.id')->where('itemable_type', 'quiz')
            ->where('quiz_attempts.status', 'submitted')->whereBetween('quiz_attempts.submitted_at', $range);
        $latest = DB::table('submission_decisions as latest')->selectRaw('max(latest.id)')
            ->whereColumn('latest.submission_id', 'submissions.id')->whereColumn('latest.revision_number', 'submissions.current_revision');
        $decisions = DB::table('curriculum_items')->join('assignment_submissions as submissions', 'submissions.assignment_id', '=', 'curriculum_items.itemable_id')
            ->join('submission_decisions as decisions', 'decisions.submission_id', '=', 'submissions.id')
            ->whereColumn('curriculum_items.course_id', 'courses.id')->where('itemable_type', 'assignment')
            ->where('decisions.id', '=', $latest)->whereBetween('decisions.created_at', $range)
            ->selectRaw('count(distinct submissions.id)');

        return $courses
            ->selectSub(DB::table('course_progress')->selectRaw('avg(percent)')->whereColumn('course_id', 'courses.id')
                ->whereBetween('last_activity_at', $range), 'average_progress')
            ->selectSub($reviews->clone()->selectRaw('count(*)'), 'approved_reviews')
            ->selectSub($reviews->selectRaw('avg(rating)'), 'average_rating')
            ->selectSub($attempts->clone()->selectRaw('count(distinct quiz_attempts.id)'), 'attempts')
            ->selectSub($attempts->where('quiz_attempts.passed', true)->selectRaw('count(distinct quiz_attempts.id)'), 'passed')
            ->selectSub($decisions->clone()->where('decisions.decision', SubmissionDecision::GRADED), 'graded_assignments')
            ->selectSub($decisions->clone()->where('decisions.decision', SubmissionDecision::ACCEPTED), 'accepted_assignments')
            ->selectSub($decisions->where('decisions.decision', SubmissionDecision::REJECTED), 'rejected_assignments');
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
