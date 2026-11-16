<?php

declare(strict_types=1);

namespace App\Filament\Resources\RolePermissions;

use App\Domain\Identity\Actions\UpdateRolePermissions;
use App\Filament\Resources\RolePermissions\Pages\ListRolePermissions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RolePermissionResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'role-permissions';

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->getAttribute('status') === 'active' && $actor->hasRole('super_admin');
    }

    public static function getModelLabel(): string
    {
        return __('staff.roles');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff.roles');
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('guard_name', 'web')->where('name', '!=', 'super_admin');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('staff.roles'))->searchable(),
            TextColumn::make('permissions_count')->counts('permissions')->label(__('staff.permissions')),
        ])->recordActions([
            Action::make('permissions')->label(__('staff.permissions'))
                ->visible(static fn (): bool => self::canViewAny())
                ->schema([Select::make('permissions')->label(__('staff.permissions'))->multiple()
                    ->options(static fn () => Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name', 'name')->all())])
                ->fillForm(static fn (Role $record): array => ['permissions' => $record->permissions->pluck('name')->all()])
                ->action(static function (Role $record, array $data, UpdateRolePermissions $updatePermissions): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    $updatePermissions->handle($actor, $record, $data['permissions'] ?? []);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRolePermissions::route('/')];
    }
}
