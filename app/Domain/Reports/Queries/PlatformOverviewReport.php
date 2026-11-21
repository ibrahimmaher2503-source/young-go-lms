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
        yield $this->row($filters);
    }

    public function count(ReportFilters $filters): int
    {
        return 1;
    }

    public function totals(ReportFilters $filters): array
    {
        // The report is a single row: build it once rather than re-running every count through rows().
        return QueryTools::sums([$this->row($filters)], $this->columns(), array_keys($this->columns()));
    }

    /** @return list<int> */
    private function row(ReportFilters $filters): array
    {
        $range = [$filters->from, $filters->to];
        $guard = (string) config('permissions.guard', 'web');
        $morph = (new User)->getMorphClass();
        $userRoles = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $rolesTable = (string) config('permission.table_names.roles', 'roles');
        $instructorSlots = implode(', ', array_fill(0, count(PublicInstructors::ROLES), '?'));

        $userRow = DB::table('users')
            ->leftJoin($userRoles, static function ($join) use ($morph, $userRoles): void {
                $join->on("{$userRoles}.model_id", '=', 'users.id')
                    ->where("{$userRoles}.model_type", '=', $morph);
            })
            ->leftJoin($rolesTable, static function ($join) use ($guard, $userRoles, $rolesTable): void {
                $join->on("{$rolesTable}.id", '=', "{$userRoles}.role_id")
                    ->where("{$rolesTable}.guard_name", '=', $guard);
            })
            ->whereBetween('users.created_at', $range)
            ->selectRaw("
                COUNT(DISTINCT users.id) AS total_users,
                COUNT(DISTINCT CASE WHEN {$rolesTable}.name = ? THEN users.id END) AS students,
                COUNT(DISTINCT CASE WHEN {$rolesTable}.name = ? THEN users.id END) AS parents,
                COUNT(DISTINCT CASE WHEN {$rolesTable}.name IN ({$instructorSlots}) THEN users.id END) AS instructors
            ", ['student', 'parent', ...PublicInstructors::ROLES])
            ->first();

        return [
            (int) ($userRow->total_users ?? 0),
            (int) ($userRow->students ?? 0),
            (int) ($userRow->parents ?? 0),
            (int) ($userRow->instructors ?? 0),
            DB::table('courses')->whereBetween('created_at', $range)->count(),
            DB::query()->fromSub(EnrollmentReport::firstEnrollments(), 'enrolled')->whereBetween('enrolled_at', $range)->count(),
            DB::table('course_completions')->whereBetween('completed_at', $range)->count(),
            DB::table('live_sessions')->whereBetween('starts_at', $range)->count(),
            DB::table('certificates')->whereBetween('issued_at', $range)->count(),
        ];
    }
}
