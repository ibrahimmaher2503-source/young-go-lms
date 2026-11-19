<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Assignments\SubmissionReview;
use App\Domain\Entitlements\EnrollmentReport;
use App\Domain\Reports\QueryTools;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use App\Models\LiveSession;
use App\Models\QuizAttempt;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/** Current learner and backlog snapshots, with session and completion dates limited to the selected window. */
final class InstructorOperationsReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::InstructorOperations;
    }

    public function columns(): array
    {
        return ['course' => 'text'] + array_fill_keys(['learners', 'upcoming_sessions', 'grading_backlog', 'help_backlog', 'new_completions'], 'int');
    }

    public function rows(ReportFilters $filters): iterable
    {
        $range = [$filters->from, $filters->to];
        $sessions = DB::table('live_sessions')->whereColumn('course_id', 'courses.id')
            ->where('status', '!=', LiveSession::CANCELLED)
            ->where('starts_at', '>=', now()->utc())->whereBetween('starts_at', $range);
        $items = DB::table('curriculum_items')->whereColumn('curriculum_items.course_id', 'courses.id');
        $assignments = $items->clone()->where('itemable_type', 'assignment')
            ->join('assignment_submissions', 'assignment_submissions.assignment_id', '=', 'curriculum_items.itemable_id')
            ->whereIn('assignment_submissions.status', SubmissionReview::AWAITING);
        $quizzes = $items->clone()->where('itemable_type', 'quiz')
            ->join('quiz_attempts', 'quiz_attempts.quiz_id', '=', 'curriculum_items.itemable_id')
            ->where('quiz_attempts.status', QuizAttempt::SUBMITTED)->where('quiz_attempts.needs_review', true);
        $help = DB::table('help_requests')->whereColumn('course_id', 'courses.id')
            ->whereIn('status', HelpRequestStatus::awaitingValues())
            ->when($filters->instructorId !== null, static fn (Builder $query) => $query->where('assigned_instructor_id', $filters->instructorId));
        $completions = DB::table('course_completions')->whereColumn('course_id', 'courses.id')->whereBetween('completed_at', $range);

        $courses = $this->courses($filters)->select('courses.id', 'courses.title')
            ->leftJoinSub(EnrollmentReport::activeCountsByCourse(), 'enrollment', 'enrollment.course_id', '=', 'courses.id')
            ->addSelect('enrollment.learners')
            ->selectSub($sessions->selectRaw('count(*)'), 'upcoming_sessions')
            ->selectSub($assignments->selectRaw('count(distinct assignment_submissions.id)'), 'assignments_awaiting')
            ->selectSub($quizzes->selectRaw('count(distinct quiz_attempts.id)'), 'essays_awaiting')
            ->selectSub($help->selectRaw('count(*)'), 'help_backlog')
            ->selectSub($completions->selectRaw('count(*)'), 'new_completions');

        foreach ($courses->lazyById(Config::integer('reports.chunk'), 'courses.id', 'id') as $course) {
            yield [
                ReportLabels::translated((string) $course->title, '#'.$course->id),
                (int) $course->learners, (int) $course->upcoming_sessions,
                (int) $course->assignments_awaiting + (int) $course->essays_awaiting,
                (int) $course->help_backlog, (int) $course->new_completions,
            ];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->courses($filters)->count();
    }

    public function totals(ReportFilters $filters): array
    {
        $columns = $this->columns();

        return QueryTools::sums($this->rows($filters), $columns, array_keys(array_diff_key($columns, ['course' => true])));
    }

    private function courses(ReportFilters $filters): Builder
    {
        return DB::table('courses')->whereExists(DB::table('course_instructors')->whereColumn('course_id', 'courses.id'))
            ->when($filters->courseIds !== null, static fn (Builder $query) => $query->whereIn('courses.id', $filters->courseIds ?? []));
    }
}
