<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Entitlements\EnrollmentReport;
use App\Domain\Reports\Dashboards\DashboardKpi;
use App\Domain\Reports\Dashboards\DashboardMoney;
use App\Domain\Reports\Dashboards\RoleDashboard;
use App\Domain\Reports\ReportCatalog;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\Audit\AuditLogResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\EssayReview\EssayReviewResource;
use App\Filament\Resources\HelpRequests\HelpRequestResource;
use App\Filament\Resources\InstructorApplications\InstructorApplicationResource;
use App\Filament\Resources\ManualPayments\ManualPaymentProofResource;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Resources\Reviews\CourseReviewResource;
use App\Filament\Resources\Settlements\SettlementResource;
use App\Filament\Resources\StaffUsers\StaffUserResource;
use App\Filament\Resources\SubmissionReview\SubmissionReviewResource;
use App\Filament\Shared\StaffNavigation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\Models\Activity;

final class WorkspaceDashboard extends Dashboard
{
    private const int QUICK_ACCESS_MAX = 8;

    private const int QUICK_ACCESS_PER_GROUP = 3;

    private const int RECENT_ACTIVITY_LIMIT = 5;

    public int $chartDays = 30;

    public function setChartDays(int $days): void
    {
        $this->chartDays = in_array($days, [7, 30, 90], true) ? $days : 30;
    }

    #[Unexposed]
    public function getHeading(): string
    {
        return $this->isAdminOverview() ? __('staff.overview') : __('staff.welcome_name', ['name' => auth()->user()?->display_name ?: auth()->user()?->name]);
    }

    #[Unexposed]
    public function getSubheading(): ?string
    {
        return $this->isAdminOverview() ? __('staff.overview_description') : null;
    }

    protected function getHeaderActions(): array
    {
        if (! $this->isAdminOverview() || ! CourseResource::canViewAny()) {
            return [];
        }

        return [Action::make('manageCourses')->label(__('staff.manage_courses'))
            ->icon('heroicon-o-academic-cap')->url(CourseResource::getUrl())
            ->extraAttributes(['data-dashboard-manage-courses' => true])];
    }

    /**
     * T-P9-07: KPI tiles and queues of the signed-in role (data from `RoleDashboard`, presentation only here).
     *
     * @return array{kpis: list<array<string, mixed>>, queues: list<array<string, mixed>>, days: int}
     */
    private function roleDashboardData(): array
    {
        $user = auth()->user();
        $panel = Filament::getCurrentOrDefaultPanel();
        if (! $user instanceof User) {
            return ['kpis' => [], 'queues' => [], 'days' => 0];
        }

        $data = app(RoleDashboard::class)->for($user, $panel->getId());
        $urls = [
            'manual_payments' => ManualPaymentProofResource::class,
            'refunds_requested' => RefundResource::class,
            'instructor_applications' => InstructorApplicationResource::class,
            'reviews_pending' => CourseReviewResource::class,
            'help_awaiting' => HelpRequestResource::class,
            'submissions_awaiting' => SubmissionReviewResource::class,
            'essays_awaiting' => EssayReviewResource::class,
            'settlements_pending' => SettlementResource::class,
            'payment_mismatches' => PaymentReconciliationPage::class,
        ];
        $registered = [...$panel->getResources(), ...$panel->getPages()];
        $row = static function (DashboardKpi $kpi, ?string $url = null): array {
            $lines = match ($kpi->kind) {
                DashboardKpi::MONEY => array_map(static fn (string $currency, int $minor): string => DashboardMoney::format($minor, $currency), array_keys($kpi->money), $kpi->money),
                DashboardKpi::PERCENT => [rtrim(rtrim(number_format((float) $kpi->value, 1), '0'), '.').'%'],
                default => [number_format((int) $kpi->value)],
            };
            $empty = $kpi->isEmpty();

            return [
                'key' => $kpi->key, 'label' => __('dashboard.kpi.'.$kpi->key), 'icon' => $kpi->icon, 'tone' => $kpi->tone, 'url' => $url, 'empty' => $empty,
                'lines' => $empty && $kpi->kind === DashboardKpi::MONEY ? ['—'] : $lines, 'raw' => $kpi->kind === DashboardKpi::MONEY ? json_encode($kpi->money) : $kpi->value,
                'secondary' => $kpi->secondary, 'secondary_label' => __('dashboard.kpi.'.$kpi->key.'_secondary'),
            ];
        };

        return [
            'kpis' => array_map(static fn (DashboardKpi $kpi): array => $row($kpi), $data->kpis),
            'queues' => array_map(static function (DashboardKpi $kpi) use ($row, $urls, $registered): array {
                $class = $urls[$kpi->key] ?? null;
                $url = null;
                if ($class !== null && in_array($class, $registered, true)) {
                    $url = is_subclass_of($class, Page::class) ? ($class::canAccess() ? $class::getUrl() : null) : ($class::canViewAny() ? $class::getUrl() : null);
                }

                return $row($kpi, $url);
            }, $data->queues),
            'days' => $data->days,
        ];
    }

    /**
     * D1: a short, grouped list of shortcuts in the sidebar's own order and groups. A section that is already a work queue above,
     * or listed twice, is left out; at most three per group and eight in all. Everything else stays one click away in the menu.
     *
     * @param  array<int|string, string>  $queueUrls
     * @return list<array{label: string, links: list<array{label: string, url: string, icon: mixed}>}>
     */
    private static function quickAccess(array $queueUrls): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $registered = [...$panel->getPages(), ...$panel->getResources()];
        $seen = array_flip(array_values($queueUrls));
        $total = 0;
        $groups = [];

        foreach (StaffNavigation::order($panel->getId()) as $key) {
            $links = [];
            foreach (StaffNavigation::classes($key, $panel->getId()) as $class) {
                if ($total >= self::QUICK_ACCESS_MAX || count($links) >= self::QUICK_ACCESS_PER_GROUP) {
                    break;
                }
                if (! in_array($class, $registered, true) || ! $class::shouldRegisterNavigation() || ! $class::canAccess()) {
                    continue;
                }
                $url = $class::getUrl();
                if (isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
                $links[] = ['label' => (string) $class::getNavigationLabel(), 'url' => $url, 'icon' => $class::getNavigationIcon()];
                $total++;
            }
            if ($links !== []) {
                $groups[] = ['label' => (string) __('navigation.groups.'.$key), 'links' => $links];
            }
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildSummary(bool $isAdmin): array
    {
        $summary = [];
        $registered = Filament::getCurrentOrDefaultPanel()->getResources();

        foreach ($registered as $resource) {
            if (! $resource::canViewAny()) {
                continue;
            }
            if ($resource === StaffUserResource::class) {
                $query = StaffUserResource::getEloquentQuery();
                $summary = [...$summary, ...($isAdmin ? [
                    ['label' => __('staff.overview_learners'), 'value' => (clone $query)->whereHas('roles', static fn ($roles) => $roles->where('name', 'student'))->count(), 'icon' => 'heroicon-o-users', 'url' => StaffUserResource::getUrl()],
                ] : [
                    ['label' => __('staff.total_users'), 'value' => (clone $query)->count(), 'icon' => 'heroicon-o-users'],
                    ['label' => __('staff.active'), 'value' => (clone $query)->where('status', 'active')->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
                    ['label' => __('staff.inactive_users'), 'value' => (clone $query)->whereIn('status', ['suspended', 'deactivated'])->count(), 'icon' => 'heroicon-o-pause-circle', 'tone' => 'info'],
                ])];
            }
            if ($isAdmin && $resource === CourseResource::class) {
                $summary[] = ['label' => __('staff.overview_courses'), 'value' => CourseResource::getEloquentQuery()->where('status', CourseStatus::Published->value)->count(), 'icon' => 'heroicon-o-academic-cap', 'tone' => 'success', 'url' => CourseResource::getUrl()];
            }
            if ($isAdmin && $resource === HelpRequestResource::class) {
                $summary[] = ['label' => __('staff.overview_help'), 'value' => HelpRequestResource::getEloquentQuery()->whereIn('status', HelpRequestStatus::openValues())->count(), 'icon' => 'heroicon-o-lifebuoy', 'tone' => 'info', 'url' => HelpRequestResource::getUrl()];
            }
        }

        return $summary;
    }

    /**
     * Permission-scoped enrollment trend series for the admin overview area chart.
     *
     * @return array{days: int, total: int, max: int, points: list<array{label: string, range: string, count: int}>}|null
     */
    private function enrollmentChart(bool $isAdmin, int $days): ?array
    {
        $user = auth()->user();
        if (! $isAdmin || ! $user instanceof User) {
            return null;
        }

        $catalog = app(ReportCatalog::class);
        if (! $catalog->canView($user, ReportKey::Enrollments)) {
            return null;
        }

        $to = now(Market::Egypt->timezone());
        $requested = ReportFilters::fromInput(['from' => $to->copy()->subDays($days - 1)->toDateString(), 'to' => $to->toDateString()]);
        $scoped = $catalog->scope($user, ReportKey::Enrollments, $requested);

        return EnrollmentReport::trend($days, $scoped->courseIds);
    }

    /**
     * @return list<array{actor: string, event: string, subject: string, subjectId: int|string|null, time: string, iso: string}>|null
     */
    private function recentActivity(bool $isAdmin): ?array
    {
        if (! $isAdmin || ! Gate::allows('audit.view')) {
            return null;
        }

        $activities = Activity::query()->where('log_name', AuditLogger::LOG_NAME)
            ->where(static fn ($query) => $query->whereNull('event')->orWhere('event', '!=', 'permission.super_admin_bypass'))
            ->with('causer')->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::RECENT_ACTIVITY_LIMIT)->get();

        return $activities->map(static function (Activity $activity): array {
            $actor = $activity->causer instanceof User && filled($activity->causer->display_name)
                ? (string) $activity->causer->display_name
                : __('staff.system_actor');
            $eventKey = 'audit-events.'.$activity->event;
            $event = Lang::has($eventKey) ? (string) __($eventKey) : __('staff.activity_recorded');

            return [
                'actor' => $actor,
                'event' => $event,
                'subject' => AuditLogResource::subjectLabel($activity->subject_type),
                'subjectId' => $activity->subject_id,
                'time' => $activity->created_at?->locale(app()->getLocale())->diffForHumans() ?? '',
                'iso' => $activity->created_at?->toIso8601String() ?? '',
            ];
        })->values()->all();
    }

    /**
     * The queue block lists its links only while at least one queue has work.
     *
     * @param  list<array{empty: bool}>  $queues
     */
    private function queueLinksShown(array $queues): bool
    {
        foreach ($queues as $queue) {
            if (! $queue['empty']) {
                return true;
            }
        }

        return false;
    }

    private function isAdminOverview(): bool
    {
        return Filament::getCurrentOrDefaultPanel()->getId() === 'admin';
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('staff.panel_'.Filament::getCurrentOrDefaultPanel()->getId());
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        $isAdmin = $this->isAdminOverview();
        $chartDays = in_array($this->chartDays, [7, 30, 90], true) ? $this->chartDays : 30;
        $role = $this->roleDashboardData();
        $summary = $this->buildSummary($isAdmin);

        return $schema->components([
            View::make('filament.staff.dashboard-intro')->viewData(['panel' => $this->getTitle()])->visible(! $isAdmin),
            View::make('filament.shared.summary-strip')->viewData(['items' => $summary, 'variant' => 'tiles'])->visible($summary !== []),
            View::make('filament.staff.dashboard-kpis')->viewData([
                ...$role,
                'isAdminOverview' => $isAdmin,
                'chart' => $this->enrollmentChart($isAdmin, $chartDays),
                'chartDays' => $chartDays,
                'recentActivity' => $this->recentActivity($isAdmin),
                'auditUrl' => ($isAdmin && Gate::allows('audit.view')) ? AuditLogResource::getUrl() : null,
            ]),
            View::make('filament.staff.dashboard-links')->viewData([
                'groups' => self::quickAccess($this->queueLinksShown($role['queues']) ? array_filter(array_column($role['queues'], 'url')) : []),
            ]),
        ]);
    }
}
