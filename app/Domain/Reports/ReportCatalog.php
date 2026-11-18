<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Reports\Queries\AiUsageReport;
use App\Domain\Reports\Queries\AssessmentResultsReport;
use App\Domain\Reports\Queries\CertificatesReport;
use App\Domain\Reports\Queries\CohortAttendanceReport;
use App\Domain\Reports\Queries\EnrollmentsReport;
use App\Domain\Reports\Queries\HelpSlaReport;
use App\Domain\Reports\Queries\InstructorEarningsReport;
use App\Domain\Reports\Queries\ReconciliationMismatchReport;
use App\Domain\Reports\Queries\RefundsReport;
use App\Domain\Reports\Queries\SalesReport;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * The one place that says who may read which report and what they may see of it. Page, export and tests all go through it, and
 * `scope()` recomputes the course restriction on the server on every call, so a forged request value never widens it.
 *
 * Access (documented defaults, PRD 6/21):
 * - `report.view` (admin, super admin): every report; the AI report also needs `ai.view_usage`.
 * - `report.finance.view` (finance): the financial reports only (sales, refunds, instructor earnings, reconciliation).
 * - An instructor (no report permission): enrollments, attendance, assessments, certificates, help SLA of the courses they are
 *   assigned to, and their own earnings. Never sales, refunds, reconciliation or AI usage.
 * Reports are aggregates or identifier-only rows: no student name, email, phone, payer or message text is in any report.
 */
final class ReportCatalog
{
    /** @return list<ReportKey> */
    public function available(User $user): array
    {
        return array_values(array_filter(ReportKey::cases(), fn (ReportKey $key): bool => $this->scopeOf($user, $key) !== ReportScope::None));
    }

    public function canView(User $user, ReportKey $key): bool
    {
        return $this->scopeOf($user, $key) !== ReportScope::None;
    }

    public function scopeOf(User $user, ReportKey $key): ReportScope
    {
        if ($user->getAttribute('status') !== 'active') {
            return ReportScope::None;
        }

        $broad = $user->can('report.view') || ($key->isFinancial() && $user->can('report.finance.view'));
        if ($key === ReportKey::AiUsage) {
            return $user->can('report.view') && AssistantAdminAccess::canViewUsage($user) ? ReportScope::All : ReportScope::None;
        }

        if ($broad) {
            return ReportScope::All;
        }

        $instructor = $user->can('panel.instructor.access') && $user->hasAnyRole(PublicInstructors::ROLES);

        return $instructor && ! in_array($key, [ReportKey::Sales, ReportKey::Refunds, ReportKey::Reconciliation], true) ? ReportScope::Own : ReportScope::None;
    }

    public function definition(ReportKey $key): ReportDefinition
    {
        return match ($key) {
            ReportKey::Sales => app(SalesReport::class),
            ReportKey::Refunds => app(RefundsReport::class),
            ReportKey::Enrollments => app(EnrollmentsReport::class),
            ReportKey::CohortAttendance => app(CohortAttendanceReport::class),
            ReportKey::AssessmentResults => app(AssessmentResultsReport::class),
            ReportKey::Certificates => app(CertificatesReport::class),
            ReportKey::InstructorEarnings => app(InstructorEarningsReport::class),
            ReportKey::AiUsage => app(AiUsageReport::class),
            ReportKey::HelpSla => app(HelpSlaReport::class),
            ReportKey::Reconciliation => app(ReconciliationMismatchReport::class),
        };
    }

    /**
     * Filters the user may run: unsupported filters are dropped, and the course restriction is the intersection of the requested
     * course, the instructor filter and (for an instructor) their own assigned courses. An instructor's earnings are pinned to
     * their own ledger.
     *
     * @throws AuthorizationException
     */
    public function scope(User $user, ReportKey $key, ReportFilters $requested): ReportFilters
    {
        $scope = $this->scopeOf($user, $key);
        if ($scope === ReportScope::None) {
            throw new AuthorizationException;
        }

        $courseId = $key->supports(ReportKey::FILTER_COURSE) ? $requested->courseId : null;
        $instructorId = $key->supports(ReportKey::FILTER_INSTRUCTOR) ? $requested->instructorId : null;
        $market = $key->supports(ReportKey::FILTER_MARKET) ? $requested->market : null;

        if ($scope === ReportScope::Own) {
            $instructorId = (int) $user->getKey();
        }

        // The ledger is keyed by instructor, not by course set: the pinned instructor is the restriction.
        if ($key === ReportKey::InstructorEarnings) {
            return $requested->scoped(null, $courseId, $instructorId, $market);
        }

        $sets = [];
        if ($courseId !== null) {
            $sets[] = [$courseId];
        }
        if ($instructorId !== null) {
            $sets[] = $this->coursesOfInstructor($instructorId);
        }

        $courseIds = null;
        foreach ($sets as $set) {
            $courseIds = $courseIds === null ? $set : array_values(array_intersect($courseIds, $set));
        }

        return $requested->scoped($courseIds, $courseId, $instructorId, $market);
    }

    /**
     * Courses for the course filter list (capped); an instructor gets their own only.
     *
     * @return array<int, string> id => title
     */
    public function courseOptions(User $user, ReportKey $key): array
    {
        $scope = $this->scopeOf($user, $key);
        if ($scope === ReportScope::None || ! $key->supports(ReportKey::FILTER_COURSE)) {
            return [];
        }

        $courses = Course::query()->select(['id', 'title'])->orderByDesc('id')->limit(300)
            ->when($scope === ReportScope::Own, fn ($query) => $query->whereIn('id', $this->coursesOfInstructor((int) $user->getKey())));

        return $courses->get()->mapWithKeys(static fn (Course $course): array => [(int) $course->getKey() => (string) $course->title])->all();
    }

    /**
     * Instructors for the instructor filter (only for users who see every instructor).
     *
     * @return array<int, string> id => name
     */
    public function instructorOptions(User $user, ReportKey $key): array
    {
        if ($this->scopeOf($user, $key) !== ReportScope::All || ! $key->supports(ReportKey::FILTER_INSTRUCTOR)) {
            return [];
        }

        return User::query()->whereHas('roles', static fn ($roles) => $roles->whereIn('name', PublicInstructors::ROLES))
            ->orderBy('name')->limit(300)->pluck('name', 'id')->map(static fn (mixed $name): string => (string) $name)->all();
    }

    /** @return list<int> */
    private function coursesOfInstructor(int $instructorId): array
    {
        return DB::table('course_instructors')->where('instructor_id', $instructorId)->pluck('course_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }
}
