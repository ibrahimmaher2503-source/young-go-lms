<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Reports\QueryTools;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Quiz results, one row per quiz placed in a course (aggregated, no learner is named). Only SUBMITTED attempts with `submitted_at`
 * in the range count. Pass rate = passed / (passed + failed); attempts waiting for a person (`passed` null) are in `needs_review`.
 */
final class AssessmentResultsReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::AssessmentResults;
    }

    public function columns(): array
    {
        return ['course' => 'text', 'quiz' => 'text', 'attempts' => 'int', 'learners' => 'int', 'average_percent' => 'percent', 'passed' => 'int', 'failed' => 'int', 'pass_rate' => 'percent', 'needs_review' => 'int'];
    }

    public function rows(ReportFilters $filters): iterable
    {
        foreach ($this->query($filters)->orderBy('quizzes.id')->get() as $quiz) {
            $passed = (int) $quiz->passed_count;
            $failed = (int) $quiz->failed_count;

            yield [
                ReportLabels::translated((string) $quiz->course_title, '#'.$quiz->course_id), ReportLabels::translated((string) $quiz->title, '#'.$quiz->id),
                (int) $quiz->attempts, (int) $quiz->learners, round((float) $quiz->average, 1), $passed, $failed,
                $passed + $failed === 0 ? 0.0 : round($passed * 100 / ($passed + $failed), 1), (int) $quiz->review_count,
            ];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return QueryTools::count($this->query($filters));
    }

    public function totals(ReportFilters $filters): array
    {
        return QueryTools::sums($this->rows($filters), $this->columns(), ['attempts', 'passed', 'failed', 'needs_review']);
    }

    private function query(ReportFilters $filters): Builder
    {
        return DB::table('quizzes')
            ->join('curriculum_items', static fn ($join) => $join->on('curriculum_items.itemable_id', '=', 'quizzes.id')->where('curriculum_items.itemable_type', 'quiz'))
            ->join('courses', 'courses.id', '=', 'curriculum_items.course_id')
            ->leftJoin('quiz_attempts', static fn ($join) => $join->on('quiz_attempts.quiz_id', '=', 'quizzes.id')
                ->where('quiz_attempts.status', 'submitted')->whereBetween('quiz_attempts.submitted_at', [$filters->from, $filters->to]))
            ->when($filters->courseIds !== null, static fn (Builder $query): Builder => $query->whereIn('curriculum_items.course_id', $filters->courseIds ?? []))
            ->groupBy('quizzes.id', 'quizzes.title', 'curriculum_items.course_id', 'courses.title')
            ->selectRaw(
                'quizzes.id, quizzes.title, curriculum_items.course_id, courses.title as course_title, count(quiz_attempts.id) as attempts, count(distinct quiz_attempts.learner_id) as learners, '
                .'coalesce(avg(quiz_attempts.percent), 0) as average, '
                .'sum(case when quiz_attempts.passed = ? then 1 else 0 end) as passed_count, sum(case when quiz_attempts.passed = ? then 1 else 0 end) as failed_count, '
                .'sum(case when quiz_attempts.needs_review = ? then 1 else 0 end) as review_count',
                [true, false, true],
            );
    }
}
