<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffUsers\Pages;

use App\Domain\Audit\AuditLogger;
use App\Filament\Resources\Audit\AuditLogResource;
use App\Filament\Resources\RolePermissions\RolePermissionResource;
use App\Filament\Resources\StaffUsers\StaffUserResource;
use App\Filament\Shared\Actions\ExportCsvAction;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

final class ListStaffUsers extends WorkspaceListRecords
{
    protected static string $resource = StaffUserResource::class;

    public function getHeading(): string
    {
        return __('staff.manage_users');
    }

    public function getSubheading(): string
    {
        return __('staff.users_description');
    }

    protected function getHeaderActions(): array
    {
        return [ExportCsvAction::make('user.export', [__('staff.name') => 'name', __('staff.email') => 'email', __('staff.status') => 'status'], 'users.csv')->icon('heroicon-o-arrow-down-tray')];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('staff.all_users')),
            'students' => Tab::make(__('staff.students'))->modifyQueryUsing(static fn (Builder $query): Builder => $query->whereHas('roles', static fn (Builder $roles): Builder => $roles->where('name', 'student')->where('guard_name', 'web'))),
            'instructors' => Tab::make(__('staff.instructors'))->modifyQueryUsing(static fn (Builder $query): Builder => $query->whereHas('roles', static fn (Builder $roles): Builder => $roles->whereIn('name', ['instructor_academy', 'instructor_independent'])->where('guard_name', 'web'))),
        ];
    }

    protected function getWorkspaceSummary(): array
    {
        if (! StaffUserResource::canViewAny()) {
            return [];
        }

        $query = StaffUserResource::getEloquentQuery();

        return [
            ['label' => __('staff.total_users'), 'value' => (clone $query)->count(), 'icon' => 'heroicon-o-users'],
            ['label' => __('staff.active'), 'value' => (clone $query)->where('status', 'active')->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['label' => __('staff.inactive_users'), 'value' => (clone $query)->whereIn('status', ['suspended', 'deactivated'])->count(), 'icon' => 'heroicon-o-pause-circle', 'tone' => 'info'],
        ];
    }

    protected function getWorkspaceSidebar(): array
    {
        if (! StaffUserResource::canViewAny()) {
            return [];
        }

        $query = StaffUserResource::getEloquentQuery();
        $total = (clone $query)->count();
        $roles = Role::query()->where('guard_name', 'web')
            ->withCount(['users' => static fn (Builder $users): Builder => $users->whereIn('users.id', (clone $query)->select('users.id'))])
            ->get()->filter(static fn (Role $role): bool => $role->users_count > 0)->sortByDesc('users_count')->values();

        $cards = [
            Section::make(__('staff.roles_overview'))->icon('heroicon-o-shield-check')
                ->headerActions([
                    Action::make('manageRoles')->label(__('staff.view_all'))->link()
                        ->url(RolePermissionResource::getUrl())
                        ->extraAttributes(['data-workspace-manage-roles' => true])
                        ->visible(static fn (): bool => RolePermissionResource::canViewAny()),
                ])
                ->schema([View::make('filament.staff.role-breakdown')->viewData(['roles' => $roles, 'total' => $total])]),
        ];

        if (Gate::allows('audit.view')) {
            $activities = Activity::query()->where('log_name', AuditLogger::LOG_NAME)
                ->with('causer')->orderByDesc('created_at')->orderByDesc('id')->limit(3)->get();
            $cards[] = Section::make(__('staff.recent_activity'))->icon('heroicon-o-clock')
                ->headerActions([Action::make('viewAudit')->label(__('staff.view_all'))->link()->url(AuditLogResource::getUrl())])
                ->schema([View::make('filament.staff.recent-activity')->viewData(['activities' => $activities])]);
        }

        return $cards;
    }
}
