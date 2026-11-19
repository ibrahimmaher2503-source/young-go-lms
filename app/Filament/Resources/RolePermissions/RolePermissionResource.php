<?php

declare(strict_types=1);

namespace App\Filament\Resources\RolePermissions;

use App\Domain\Identity\Actions\UpdateRolePermissions;
use App\Filament\Resources\RolePermissions\Pages\ListRolePermissions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RolePermissionResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'role-permissions';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shield-check';
    }

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
            TextColumn::make('name')->extraAttributes(['tabindex' => '0', 'class' => 'yg-role-name'])->label(__('staff.roles'))->formatStateUsing(static fn (string $state): string => Lang::has('roles.'.$state) ? __('roles.'.$state) : $state)
                ->searchable(query: static function (Builder $query, string $search): Builder {
                    $names = array_keys(array_filter(__('roles'), static fn (string $label): bool => mb_stripos($label, $search) !== false));

                    return $query->where(static fn (Builder $roles): Builder => $roles->where('name', 'like', '%'.$search.'%')->orWhereIn('name', $names));
                }),
            TextColumn::make('permissions_count')->counts('permissions')->label(__('staff.permission_count'))->sortable(),
        ])->recordActions([
            Action::make('permissions')->label(__('staff.permissions'))->modalWidth(Width::FourExtraLarge)->modalDescription(__('staff.permissions_help'))->modalSubmitActionLabel(__('staff.save_changes'))
                ->visible(static fn (): bool => self::canViewAny())->extraAttributes(static fn (Role $record): array => ['data-workspace-permission-action' => (string) $record->id])
                ->schema([CheckboxList::make('permissions')->label(__('staff.permissions'))->helperText(__('staff.permissions_scroll_help'))->bulkToggleable()->columns(['default' => 1, 'md' => 2])->extraAttributes(['class' => 'yg-permission-options'])
                    ->options(static fn () => Permission::query()->where('guard_name', 'web')->orderBy('name')->get()->mapWithKeys(static fn (Permission $permission): array => [$permission->name => __('permissions.'.$permission->name)])->all())->searchable()])
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
