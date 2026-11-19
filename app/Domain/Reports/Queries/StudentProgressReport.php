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

final class StudentProgressReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::StudentProgress;
    }

    public function columns(): array
    {
        return [
            'learner_reference' => 'int', 'course' => 'text', 'enrolled_at' => 'datetime', 'last_activity_at' => 'datetime',
            'completed_lessons' => 'int', 'attended_sessions' => 'int', 'attempts' => 'int', 'passed' => 'int',
            'submitted_assignments' => 'int', 'issued' => 'int',
        ];
    }

    public function rows(ReportFilters $filters): iterable
    {
        $range = [$filters->from, $filters->to];
        $items = DB::table('curriculum_items as items')->whereColumn('items.course_id', 'enrolled.course_id');
        $lesson = $items->clone()->where('items.itemable_type', 'lesson')
            ->join('lesson_progress as progress', 'progress.curriculum_item_id', '=', 'items.id')
            ->whereColumn('progress.learner_id', 'enrolled.learner_id');
        $attendance = DB::table('live_sessions as sessions')->whereColumn('sessions.course_id', 'enrolled.course_id')
            ->join('live_attendance as attendance', 'attendance.live_session_id', '=', 'sessions.id')
            ->whereColumn('attendance.learner_id', 'enrolled.learner_id')->whereIn('attendance.status', ['present', 'late'])
            ->whereBetween('sessions.starts_at', $range);
        $quiz = $items->clone()->where('items.itemable_type', 'quiz')
            ->join('quiz_attempts as attempts', 'attempts.quiz_id', '=', 'items.itemable_id')
            ->whereColumn('attempts.learner_id', 'enrolled.learner_id')->where('attempts.status', 'submitted')
            ->whereBetween('attempts.submitted_at', $range);
        $assignments = $items->clone()->where('items.itemable_type', 'assignment')
            ->join('assignment_submissions as submissions', 'submissions.assignment_id', '=', 'items.itemable_id')
            ->join('submission_revisions as revisions', 'revisions.submission_id', '=', 'submissions.id')
            ->whereColumn('submissions.learner_id', 'enrolled.learner_id')->whereBetween('revisions.submitted_at', $range);
        $certificates = DB::table('certificates')->whereColumn('course_id', 'enrolled.course_id')
            ->whereColumn('learner_id', 'enrolled.learner_id')->whereBetween('issued_at', $range);

        $learners = $this->enrollments($filters)->join('courses', 'courses.id', '=', 'enrolled.course_id')
            ->select('enrolled.learner_id', 'enrolled.enrolled_at', 'courses.title')
            ->selectSub($lesson->clone()->whereBetween('progress.last_accessed_at', $range)->selectRaw('max(progress.last_accessed_at)'), 'last_activity_at')
            ->selectSub($lesson->whereBetween('progress.completed_at', $range)->selectRaw('count(*)'), 'completed_lessons')
            ->selectSub($attendance->selectRaw('count(*)'), 'attended_sessions')
            ->selectSub($quiz->clone()->selectRaw('count(*)'), 'attempts')
            ->selectSub($quiz->where('attempts.passed', true)->selectRaw('count(*)'), 'passed')
            ->selectSub($assignments->selectRaw('count(distinct submissions.id)'), 'submitted_assignments')
            ->selectSub($certificates->selectRaw('count(*)'), 'issued')
            ->orderBy('enrolled.course_id')->orderBy('enrolled.learner_id');

        foreach ($learners->lazy(Config::integer('reports.chunk')) as $row) {
            yield [
                (int) $row->learner_id, ReportLabels::translated((string) $row->title), ReportLabels::datetime($row->enrolled_at),
                ReportLabels::datetime($row->last_activity_at), (int) $row->completed_lessons, (int) $row->attended_sessions,
                (int) $row->attempts, (int) $row->passed, (int) $row->submitted_assignments, (int) $row->issued,
            ];
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->enrollments($filters)->count();
    }

    public function totals(ReportFilters $filters): array
    {
        return QueryTools::sums($this->rows($filters), $this->columns(), ['completed_lessons', 'attended_sessions', 'attempts', 'passed', 'submitted_assignments', 'issued']);
    }

    private function enrollments(ReportFilters $filters): Builder
    {
        return DB::query()->fromSub(EnrollmentReport::firstEnrollments(), 'enrolled')
            ->where('enrolled_at', '<=', $filters->to)
            ->when($filters->courseIds !== null, static fn (Builder $query) => $query->whereIn('enrolled.course_id', $filters->courseIds ?? []));
    }
}
