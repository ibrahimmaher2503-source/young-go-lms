<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Entitlements\EnrollmentReport;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Reports\QueryTools;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PlatformOverviewReport implements ReportDefinition
{
    public function key(): ReportKey
    {
        return ReportKey::PlatformOverview;
    }

    public function columns(): array
    {
        return array_fill_keys(['users', 'students', 'parents', 'instructors', 'courses', 'new_enrollments', 'new_completions', 'sessions', 'issued'], 'int');
    }

    public function rows(ReportFilters $filters): iterable
    {
        $range = [$filters->from, $filters->to];
        $users = User::query()->whereBetween('users.created_at', $range);

        yield [
            $users->clone()->count(),
            $users->clone()->role('student')->count(),
            $users->clone()->role('parent')->count(),
            $users->clone()->role(PublicInstructors::ROLES)->count(),
            DB::table('courses')->whereBetween('created_at', $range)->count(),
            DB::query()->fromSub(EnrollmentReport::firstEnrollments(), 'enrolled')->whereBetween('enrolled_at', $range)->count(),
            DB::table('course_completions')->whereBetween('completed_at', $range)->count(),
            DB::table('live_sessions')->whereBetween('starts_at', $range)->count(),
            DB::table('certificates')->whereBetween('issued_at', $range)->count(),
        ];
    }

    public function count(ReportFilters $filters): int
    {
        return 1;
    }

    public function totals(ReportFilters $filters): array
    {
        return QueryTools::sums($this->rows($filters), $this->columns(), array_keys($this->columns()));
    }
}
