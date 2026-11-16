<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffUsers;

use App\Domain\Identity\Actions\AssignStaffRoles;
use App\Filament\Resources\StaffUsers\Pages\ListStaffUsers;
use App\Filament\Shared\Actions\ExportCsvAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class StaffUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff-users';

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
            TextColumn::make('name')->label(__('staff.name'))->searchable(),
            TextColumn::make('email')->label(__('staff.email'))->searchable(),
            TextColumn::make('status')->label(__('staff.status'))->badge(),
        ])->filters([
            SelectFilter::make('status')->label(__('staff.status'))->options([
                'active' => __('staff.active'),
                'suspended' => __('staff.suspended'),
                'deactivated' => __('staff.deactivated'),
            ]),
        ])->headerActions([
            ExportCsvAction::make('user.export', [
                __('staff.name') => 'name',
                __('staff.email') => 'email',
                __('staff.status') => 'status',
            ], 'users.csv'),
        ])->recordActions([
            Action::make('assign_roles')->label(__('staff.roles'))
                ->visible(static fn (User $record): bool => auth()->user()?->getAttribute('status') === 'active'
                    && auth()->user()->hasRole('super_admin') && ! $record->hasRole('super_admin'))
                ->schema([Select::make('roles')->label(__('staff.roles'))->multiple()->options(
                    static fn () => Role::query()->where('guard_name', 'web')->where('name', '!=', 'super_admin')->pluck('name', 'name')->all(),
                )])
                ->fillForm(static fn (User $record): array => ['roles' => $record->getRoleNames()->all()])
                ->action(static function (User $record, array $data, AssignStaffRoles $assignRoles): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    $assignRoles->handle($actor, $record, $data['roles'] ?? []);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStaffUsers::route('/')];
    }
}
