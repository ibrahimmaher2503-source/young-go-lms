<?php

declare(strict_types=1);

namespace App\Domain\Reports;

/** The reports of T-P9-06 (PRD 21). The value is the URL key and the lang key (`reports.names.<value>`). */
enum ReportKey: string
{
    case Sales = 'sales';
    case Refunds = 'refunds';
    case Enrollments = 'enrollments';
    case CohortAttendance = 'cohort_attendance';
    case AssessmentResults = 'assessment_results';
    case Certificates = 'certificates';
    case InstructorEarnings = 'instructor_earnings';
    case AiUsage = 'ai_usage';
    case HelpSla = 'help_sla';
    case Reconciliation = 'reconciliation';

    public const string FILTER_COURSE = 'course';

    public const string FILTER_INSTRUCTOR = 'instructor';

    public const string FILTER_MARKET = 'market';

    /** Money reports: finance may read them (`report.finance.view`). */
    public function isFinancial(): bool
    {
        return in_array($this, [self::Sales, self::Refunds, self::InstructorEarnings, self::Reconciliation], true);
    }

    /** @return list<string> filters beyond the date range that this report honours; the others are ignored, never guessed */
    public function filters(): array
    {
        return match ($this) {
            self::Sales, self::Refunds, self::InstructorEarnings => [self::FILTER_COURSE, self::FILTER_INSTRUCTOR, self::FILTER_MARKET],
            self::Reconciliation => [self::FILTER_MARKET],
            self::AiUsage => [],
            default => [self::FILTER_COURSE, self::FILTER_INSTRUCTOR],
        };
    }

    public function supports(string $filter): bool
    {
        return in_array($filter, $this->filters(), true);
    }

    public function label(): string
    {
        return __('reports.names.'.$this->value);
    }
}
