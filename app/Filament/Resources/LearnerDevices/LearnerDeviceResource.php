<?php

declare(strict_types=1);

namespace App\Filament\Resources\LearnerDevices;

use App\Domain\Identity\Actions\ResetUserDevices;
use App\Domain\Identity\Models\UserDevice;
use App\Domain\Identity\Models\UserSession;
use App\Filament\Resources\LearnerDevices\Pages\ListLearnerDevices;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Support view of learner sign-ins: how many devices and live sessions each account has, with the device reset (YG-GAP-SEC-001). */
final class LearnerDeviceResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'learner-devices';

    public static function getModelLabel(): string
    {
        return __('devices.learner');
    }

    public static function getPluralModelLabel(): string
    {
        return __('devices.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-device-phone-mobile';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('user.manage');
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

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        return User::query()->role((array) config('security.sessions.roles'))
            ->addSelect([
                'devices_count' => UserDevice::query()->selectRaw('count(*)')->whereColumn('user_devices.user_id', 'users.id'),
                'sessions_count' => UserSession::query()->selectRaw('count(*)')->whereColumn('user_sessions.user_id', 'users.id')->whereNull('signed_out_at'),
                'last_seen' => UserDevice::query()->selectRaw('max(last_seen_at)')->whereColumn('user_devices.user_id', 'users.id'),
            ])->withCasts(['last_seen' => 'datetime']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('devices.learner'))->searchable(),
                TextColumn::make('email')->label(__('devices.email'))->searchable()->placeholder('-'),
                TextColumn::make('devices_count')->label(__('devices.devices'))->state(static fn (User $r): string => $r->getAttribute('devices_count').' / '.config('security.sessions.max_devices')),
                TextColumn::make('sessions_count')->label(__('devices.sessions'))->state(static fn (User $r): string => $r->getAttribute('sessions_count').' / '.config('security.sessions.max_concurrent')),
                TextColumn::make('last_seen')->label(__('devices.last_seen'))->state(static fn (User $r): string => $r->getAttribute('last_seen')?->isoFormat('LLL') ?? '-'),
            ])
            ->recordActions([
                Action::make('reset')->label(__('devices.reset'))->icon('heroicon-o-arrow-path')->color('danger')->requiresConfirmation()->modalDescription(__('devices.reset_confirm'))
                    ->action(static function (User $record): void {
                        $actor = Auth::user();
                        abort_unless($actor instanceof User, 401);
                        try {
                            $removed = app(ResetUserDevices::class)($actor, $record);
                            Notification::make()->success()->title(__('devices.reset_done', ['count' => $removed]))->send();
                        } catch (AuthorizationException) {
                            Notification::make()->danger()->title(__('devices.not_allowed'))->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLearnerDevices::route('/')];
    }
}
