<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Catalog\CourseStatus;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\HelpRequests\HelpRequestResource;
use App\Filament\Resources\StaffUsers\StaffUserResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;

final class WorkspaceDashboard extends Dashboard
{
    #[Unexposed]
    public function getHeading(): string
    {
        return $this->isAdminOverview() ? __('staff.overview') : __('staff.welcome_name', ['name' => auth()->user()?->name]);
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
        $resources = [];
        $summary = [];
        foreach (Filament::getCurrentOrDefaultPanel()->getResources() as $resource) {
            if (! $resource::canViewAny()) {
                continue;
            }
            $resources[] = ['label' => $resource::getNavigationLabel(), 'url' => $resource::getUrl(), 'icon' => $resource::getNavigationIcon()];
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
            View::make('filament.staff.dashboard-links')->viewData(['resources' => $resources]),
        ]);
    }
}
