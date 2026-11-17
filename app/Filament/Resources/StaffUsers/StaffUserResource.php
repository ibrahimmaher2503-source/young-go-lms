<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffUsers;

use App\Domain\Identity\Actions\AssignStaffRoles;
use App\Filament\Resources\StaffUsers\Pages\ListStaffUsers;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class StaffUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff-users';

    public static function getModelLabel(): string
    {
        return __('staff.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff.users');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-users';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('roles');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('user.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->view('filament.shared.tables.person')->label(__('staff.name'))->searchable(),
            TextColumn::make('email')->label(__('staff.email'))->tooltip(static fn (User $record): string => $record->email)->extraAttributes(['dir' => 'ltr', 'class' => 'yg-email'])->searchable(),
            TextColumn::make('roles.name')->label(__('staff.roles'))->badge()->color(static fn (string $state): string => match ($state) {
                'student', 'parent' => 'info', 'super_admin', 'admin' => 'success', default => 'primary'
            })->formatStateUsing(static fn (string $state): string => __('roles.'.$state)),
            StatusBadgeColumn::make('status', ['active' => 'success', 'suspended' => 'danger', 'deactivated' => 'gray'])
                ->label(__('staff.status'))
                ->extraAttributes(['class' => 'yg-user-status'])
                ->formatStateUsing(static fn (string $state): string => __('staff.'.$state)),
            LocalizedDateColumn::make('created_at', pattern: 'yyyy-MM-dd')->label(__('staff.joined'))->extraAttributes(['dir' => 'ltr'])->sortable(),
        ])->filters([
            SelectFilter::make('role')->label(__('staff.roles'))->relationship('roles', 'name')->getOptionLabelFromRecordUsing(static fn (Role $role): string => __('roles.'.$role->name)),
            SelectFilter::make('status')->label(__('staff.status'))->options([
                'active' => __('staff.active'),
                'suspended' => __('staff.suspended'),
                'deactivated' => __('staff.deactivated'),
            ]),
        ])->deferFilters(false)
            ->searchPlaceholder(__('staff.search_users'))
            ->filtersTriggerAction(static fn (Action $action): Action => $action->button()->label(__('staff.filter'))->color('gray')->outlined()->badge(null)->extraAttributes(['class' => 'yg-filter-trigger']))
            ->recordActionsColumnLabel(__('staff.actions'))
            ->recordActions([
                ActionGroup::make([Action::make('assign_roles')->label(__('staff.assign_roles'))->modalHeading(__('staff.assign_roles'))->modalDescription(static fn (User $record): string => __('staff.role_assignment_help', ['name' => $record->name]))->modalSubmitActionLabel(__('staff.save_changes'))
                    ->visible(static fn (User $record): bool => auth()->user()?->getAttribute('status') === 'active'
                        && auth()->user()->hasRole('super_admin') && ! $record->hasRole('super_admin'))
                    ->schema([Select::make('roles')->label(__('staff.roles'))->multiple()->options(
                        static fn () => Role::query()->where('guard_name', 'web')->where('name', '!=', 'super_admin')->get()->mapWithKeys(static fn (Role $role): array => [$role->name => __('roles.'.$role->name)])->all(),
                    )])
                    ->fillForm(static fn (User $record): array => ['roles' => $record->getRoleNames()->all()])
                    ->action(static function (User $record, array $data, AssignStaffRoles $assignRoles): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        $assignRoles->handle($actor, $record, $data['roles'] ?? []);
                    })])->icon('heroicon-o-ellipsis-horizontal')->label(__('staff.actions')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStaffUsers::route('/')];
    }
}
