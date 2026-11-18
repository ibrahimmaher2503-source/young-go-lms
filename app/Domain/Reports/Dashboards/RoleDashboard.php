<?php

declare(strict_types=1);

namespace App\Domain\Reports\Dashboards;

use App\Domain\AI\Help\HelpRequestAccess;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Assignments\ReviewAccess;
use App\Domain\Assignments\SubmissionReview;
use App\Domain\Commerce\Queries\ManualPaymentQueue;
use App\Domain\Commerce\Queries\ReconciliationReport;
use App\Domain\Commerce\Queries\RefundQueue;
use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewStatus;
use App\Domain\Entitlements\EnrollmentReport;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Domain\Instructors\ApplicationStatus;
use App\Domain\Instructors\Models\InstructorApplication;
use App\Domain\Reports\ReportCatalog;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Shared\Markets\Market;
use App\Models\AssignmentSubmission;
use App\Models\HelpRequest;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * T-P9-07: the KPIs and work queues of the admin, finance and instructor dashboards. Report-backed KPIs are NOT recomputed here:
 * they call the T-P9-06 report definitions through `ReportCatalog::scope()` (so an instructor only ever sees their own courses and
 * earnings, and the numbers equal the report totals for the same period). They are cached briefly per user
 * (`reports.dashboard_cache_seconds`). Queues are cheap live counts and are never cached. Every tile is gated by the same
 * permission that gates the page it links to; a user who may see nothing gets an empty `DashboardData`.
 */
final class RoleDashboard
{
    public function __construct(private readonly ReportCatalog $catalog) {}

    public function for(User $user, string $panel): DashboardData
    {
        $days = Config::integer('reports.dashboard_days');
        if ($user->getAttribute('status') !== 'active') {
            return new DashboardData([], [], $days);
        }

        $seconds = Config::integer('reports.dashboard_cache_seconds');
        $build = fn (): array => match ($panel) {
            'admin' => $this->adminKpis($user, $days),
            'finance' => $this->financeKpis($user, $days),
            default => $this->instructorKpis($user, $days),
        };
        $kpis = $seconds > 0 ? Cache::remember('dashboard:v1:'.$panel.':'.$user->getKey(), $seconds, $build) : $build();

        $queues = match ($panel) {
            'admin' => $this->adminQueues($user),
            'finance' => $this->financeQueues($user),
            default => $this->instructorQueues($user),
        };

        return new DashboardData($kpis, $queues, $days);
    }

    /** @return list<DashboardKpi> */
    private function adminKpis(User $user, int $days): array
    {
        return [
            ...$this->salesKpis($user, $days),
            ...$this->learnerKpis($user, $days, ownRate: false),
            ...$this->aiKpis($user, $days),
        ];
    }

    /** @return list<DashboardKpi> */
    private function financeKpis(User $user, int $days): array
    {
        $kpis = $this->salesKpis($user, $days);

        if ($user->can('ledger.view') || $user->can('settlement.manage')) {
            $kpis[] = DashboardKpi::money('instructor_balances', $this->ledgerBalances([LedgerStatus::Accrued, LedgerStatus::Pending, LedgerStatus::Approved]), 'heroicon-o-banknotes', 'info');
            $kpis[] = DashboardKpi::money('instructor_held', $this->ledgerBalances([LedgerStatus::Held]), 'heroicon-o-pause-circle', 'info');
        }

        return $kpis;
    }

    /** @return list<DashboardKpi> */
    private function instructorKpis(User $user, int $days): array
    {
        $kpis = $this->learnerKpis($user, $days, ownRate: true);

        $totals = $this->totals($user, ReportKey::InstructorEarnings, $days);
        if ($totals !== null) {
            $kpis[] = DashboardKpi::money('earnings', $this->money($totals, 'net'), 'heroicon-o-banknotes', 'success');
        }

        return $kpis;
    }

    /** @return list<DashboardKpi> */
    private function salesKpis(User $user, int $days): array
    {
        $kpis = [];
        $sales = $this->totals($user, ReportKey::Sales, $days);
        if ($sales !== null) {
            $kpis[] = DashboardKpi::money('revenue', $this->money($sales, 'net'), 'heroicon-o-banknotes', 'success');
            $kpis[] = DashboardKpi::count('orders', $this->count($sales, 'orders'), 'heroicon-o-shopping-cart');
        }

        $refunds = $this->totals($user, ReportKey::Refunds, $days);
        if ($refunds !== null) {
            $kpis[] = DashboardKpi::money('refunds', $this->money($refunds, 'refund_amount'), 'heroicon-o-arrow-uturn-left', 'info', $this->count($refunds, 'refund_count'));
        }

        return $kpis;
    }

    /** @return list<DashboardKpi> */
    private function learnerKpis(User $user, int $days, bool $ownRate): array
    {
        $kpis = [];
        $filters = $this->filters($user, ReportKey::Enrollments, $days);
        if ($filters === null) {
            return [];
        }

        $kpis[] = DashboardKpi::count($ownRate ? 'my_learners' : 'active_learners', EnrollmentReport::activeLearners($filters->courseIds), 'heroicon-o-users');

        $totals = $this->catalog->definition(ReportKey::Enrollments)->totals($filters);
        $kpis[] = DashboardKpi::count('completions', $this->count($totals, 'new_completions'), 'heroicon-o-check-badge', 'success');

        if ($ownRate) {
            $learners = $this->count($totals, 'total_learners');
            $kpis[] = DashboardKpi::percent('completion_rate', $learners === 0 ? 0.0 : round($this->count($totals, 'total_completions') * 100 / $learners, 1), 'heroicon-o-chart-pie');
        }

        return $kpis;
    }

    /** @return list<DashboardKpi> */
    private function aiKpis(User $user, int $days): array
    {
        $totals = $this->totals($user, ReportKey::AiUsage, $days);
        if ($totals === null) {
            return [];
        }

        return [
            DashboardKpi::count('ai_requests', $this->count($totals, 'ai_requests'), 'heroicon-o-sparkles', 'primary', $this->count($totals, 'ai_errors')),
            DashboardKpi::money('ai_cost', $this->money($totals, 'ai_cost'), 'heroicon-o-currency-dollar', 'info'),
        ];
    }

    /** @return list<DashboardKpi> */
    private function adminQueues(User $user): array
    {
        $queues = [];
        if ($user->can('payment.view') || $user->can('payment.approve_manual')) {
            $summary = ManualPaymentQueue::summary();
            $queues[] = DashboardKpi::count('manual_payments', $summary['pending'], 'heroicon-o-receipt-percent', 'info', $summary['overdue']);
        }
        if ($user->can('payment.view') || $user->can('payment.refund')) {
            $queues[] = DashboardKpi::count('refunds_requested', RefundQueue::summary()['requested'], 'heroicon-o-arrow-uturn-left', 'info');
        }
        if ($user->can('instructor.manage')) {
            $queues[] = DashboardKpi::count('instructor_applications', InstructorApplication::query()->where('status', ApplicationStatus::Pending->value)->count(), 'heroicon-o-user-plus', 'info');
        }
        if ($user->can('review.moderate')) {
            $queues[] = DashboardKpi::count('reviews_pending', CourseReview::query()->where('status', ReviewStatus::Pending->value)->count(), 'heroicon-o-star', 'info');
        }

        return [...$queues, ...$this->helpQueue($user)];
    }

    /** @return list<DashboardKpi> */
    private function financeQueues(User $user): array
    {
        $queues = [];
        if ($user->can('payment.view') || $user->can('payment.approve_manual')) {
            $summary = ManualPaymentQueue::summary();
            $queues[] = DashboardKpi::count('manual_payments', $summary['pending'], 'heroicon-o-receipt-percent', 'info', $summary['overdue']);
            $queues[] = DashboardKpi::count('payment_mismatches', app(ReconciliationReport::class)->openCount(), 'heroicon-o-exclamation-triangle', 'danger');
        }
        if ($user->can('payment.view') || $user->can('payment.refund')) {
            $queues[] = DashboardKpi::count('refunds_requested', RefundQueue::summary()['requested'], 'heroicon-o-arrow-uturn-left', 'info');
        }
        if ($user->can('ledger.view') || $user->can('settlement.manage')) {
            $pending = Settlement::query()->whereIn('status', [SettlementStatus::Draft->value, SettlementStatus::Approved->value])
                ->selectRaw('currency, count(*) as batches, sum(total_minor) as total')->groupBy('currency')->get();
            $money = [];
            $batches = 0;
            foreach ($pending as $row) {
                $money[(string) $row->getAttribute('currency')] = (int) $row->getAttribute('total');
                $batches += (int) $row->getAttribute('batches');
            }
            $queues[] = DashboardKpi::money('settlements_pending', $money, 'heroicon-o-clipboard-document-check', 'info', $batches);
        }

        return $queues;
    }

    /** @return list<DashboardKpi> */
    private function instructorQueues(User $user): array
    {
        $queues = [];
        $courseIds = ReviewAccess::courseIds($user);
        if ($courseIds !== []) {
            $awaiting = AssignmentSubmission::query()->whereIn('status', SubmissionReview::AWAITING)
                ->whereHas('assignment.item', static fn ($item) => $item->whereIn('course_id', $courseIds));
            $overdue = (clone $awaiting)->whereHas('assignment', static fn ($assignment) => $assignment->whereNotNull('due_at')->where('due_at', '<', now()));
            $queues[] = DashboardKpi::count('submissions_awaiting', $awaiting->count(), 'heroicon-o-document-check', 'info', $overdue->count());

            $queues[] = DashboardKpi::count('essays_awaiting', QuizAttempt::query()->where('status', QuizAttempt::SUBMITTED)->where('needs_review', true)
                ->whereHas('quiz.item', static fn ($item) => $item->whereIn('course_id', $courseIds))->count(), 'heroicon-o-pencil-square', 'info');
        }

        return [...$queues, ...$this->helpQueue($user)];
    }

    /**
     * Help requests awaiting a staff reply, scoped like the help list (assigned ones for an instructor), with the overdue count.
     *
     * @return list<DashboardKpi>
     */
    private function helpQueue(User $user): array
    {
        if (! HelpRequestAccess::isStaff($user)) {
            return [];
        }

        $awaiting = HelpRequestAccess::scope(HelpRequest::query(), $user)->whereIn('status', HelpRequestStatus::awaitingValues());
        $overdue = (clone $awaiting)->whereNotNull('due_at')->where('due_at', '<', now());

        return [DashboardKpi::count('help_awaiting', $awaiting->count(), 'heroicon-o-lifebuoy', 'info', $overdue->count())];
    }

    /**
     * @param  list<LedgerStatus>  $statuses
     * @return array<string, int> currency => minor units
     */
    private function ledgerBalances(array $statuses): array
    {
        $rows = LedgerEntry::query()->whereIn('status', array_map(static fn (LedgerStatus $status): string => $status->value, $statuses))
            ->selectRaw('currency, sum(amount_minor) as total')->groupBy('currency')->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->getAttribute('currency')] = (int) $row->getAttribute('total');
        }

        return $out;
    }

    private function filters(User $user, ReportKey $key, int $days): ?ReportFilters
    {
        if (! $this->catalog->canView($user, $key)) {
            return null;
        }

        $to = now(Market::Egypt->timezone());
        $requested = ReportFilters::fromInput(['from' => $to->copy()->subDays($days - 1)->toDateString(), 'to' => $to->toDateString()]);

        return $this->catalog->scope($user, $key, $requested);
    }

    /** @return list<array{key: string, currency: string|null, value: int|float, money: bool}>|null null = the user may not read this report */
    private function totals(User $user, ReportKey $key, int $days): ?array
    {
        $filters = $this->filters($user, $key, $days);

        return $filters === null ? null : $this->catalog->definition($key)->totals($filters);
    }

    /**
     * @param  list<array{key: string, currency: string|null, value: int|float, money: bool}>  $totals
     * @return array<string, int> currency => minor units
     */
    private function money(array $totals, string $key): array
    {
        $out = [];
        foreach ($totals as $total) {
            if ($total['key'] === $key && $total['currency'] !== null) {
                $out[$total['currency']] = ($out[$total['currency']] ?? 0) + (int) round($total['value'] * 100);
            }
        }

        return $out;
    }

    /** @param  list<array{key: string, currency: string|null, value: int|float, money: bool}>  $totals */
    private function count(array $totals, string $key): int
    {
        $sum = 0;
        foreach ($totals as $total) {
            if ($total['key'] === $key) {
                $sum += (int) $total['value'];
            }
        }

        return $sum;
    }
}
