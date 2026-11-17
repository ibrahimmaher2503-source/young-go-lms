<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\StaffUsers\StaffUserResource;
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
        return __('staff.welcome_name', ['name' => auth()->user()?->name]);
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
                $summary = [
                    ['label' => __('staff.total_users'), 'value' => (clone $query)->count(), 'icon' => 'heroicon-o-users'],
                    ['label' => __('staff.active'), 'value' => (clone $query)->where('status', 'active')->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
                    ['label' => __('staff.inactive_users'), 'value' => (clone $query)->whereIn('status', ['suspended', 'deactivated'])->count(), 'icon' => 'heroicon-o-pause-circle', 'tone' => 'info'],
                ];
            }
        }

        return $schema->components([
            View::make('filament.staff.dashboard-intro')->viewData(['panel' => $this->getTitle()]),
            View::make('filament.shared.summary-strip')->viewData(['items' => $summary])->visible($summary !== []),
            View::make('filament.staff.dashboard-links')->viewData(['resources' => $resources]),
        ]);
    }
}
