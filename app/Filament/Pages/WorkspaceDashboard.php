<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Reports\Dashboards\DashboardKpi;
use App\Domain\Reports\Dashboards\DashboardMoney;
use App\Domain\Reports\Dashboards\RoleDashboard;
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

final class WorkspaceDashboard extends Dashboard
{
    private const int QUICK_ACCESS_MAX = 8;

    private const int QUICK_ACCESS_PER_GROUP = 3;

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
            foreach (StaffNavigation::GROUPS[$key] as $class) {
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

    /** The queue block lists its links only while at least one queue has work; when all are clear it is one summary line. */
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
        $summary = [];
        $role = $this->roleDashboardData();
        foreach (Filament::getCurrentOrDefaultPanel()->getResources() as $resource) {
            if (! $resource::canViewAny()) {
                continue;
            }
            if ($resource === StaffUserResource::class) {
                $query = StaffUserResource::getEloquentQuery();
                $summary = [...$summary, ...($this->isAdminOverview() ? [
                    ['label' => __('staff.overview_learners'), 'value' => (clone $query)->whereHas('roles', static fn ($roles) => $roles->where('name', 'student'))->count(), 'icon' => 'heroicon-o-users'],
                ] : [
                    ['label' => __('staff.total_users'), 'value' => (clone $query)->count(), 'icon' => 'heroicon-o-users'],
                    ['label' => __('staff.active'), 'value' => (clone $query)->where('status', 'active')->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
                    ['label' => __('staff.inactive_users'), 'value' => (clone $query)->whereIn('status', ['suspended', 'deactivated'])->count(), 'icon' => 'heroicon-o-pause-circle', 'tone' => 'info'],
                ])];
            }
            if ($this->isAdminOverview() && $resource === CourseResource::class) {
                $summary[] = ['label' => __('staff.overview_courses'), 'value' => CourseResource::getEloquentQuery()->where('status', CourseStatus::Published->value)->count(), 'icon' => 'heroicon-o-academic-cap'];
            }
            if ($this->isAdminOverview() && $resource === HelpRequestResource::class) {
                $summary[] = ['label' => __('staff.overview_help'), 'value' => HelpRequestResource::getEloquentQuery()->whereIn('status', HelpRequestStatus::openValues())->count(), 'icon' => 'heroicon-o-lifebuoy', 'tone' => 'info'];
            }
        }

        return $schema->components([
            View::make('filament.staff.dashboard-intro')->viewData(['panel' => $this->getTitle()])->visible(! $this->isAdminOverview()),
            View::make('filament.shared.summary-strip')->viewData(['items' => $summary])->visible($summary !== []),
            View::make('filament.staff.dashboard-kpis')->viewData($role),
            View::make('filament.staff.dashboard-links')->viewData(['groups' => self::quickAccess($this->queueLinksShown($role['queues']) ? array_filter(array_column($role['queues'], 'url')) : [])]),
        ]);
    }
}
