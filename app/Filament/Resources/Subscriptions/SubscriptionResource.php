<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions;

use App\Domain\Subscriptions\Actions\CancelSubscription;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Shared\Tables\CompactActions;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * T-P8-08: staff list of subscriptions (admin and finance panels), read for `payment.view`. Finance and admin
 * (payment.approve_manual) can cancel one: access stays until the end of the paid period (CancelSubscription).
 */
final class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $slug = 'subscriptions';

    public static function getModelLabel(): string
    {
        return __('subscriptions.subscription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('subscriptions.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-arrow-path';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('payment.view') || Gate::allows('payment.approve_manual');
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

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['plan', 'learner', 'payer', 'periods']);
    }

    public static function table(Table $table): Table
    {
        return $table->extraAttributes(['class' => 'yg-compact-table'], merge: true)->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('learner.name')->label(__('subscriptions.learner'))->searchable(),
                TextColumn::make('payer.name')->visibleFrom('md')->label(__('subscriptions.payer'))->searchable(),
                TextColumn::make('plan')->label(__('subscriptions.plan'))->state(static fn (Subscription $s): string => (string) $s->plan?->name),
                TextColumn::make('period_end')->visibleFrom('md')->label(__('subscriptions.period_end'))
                    ->state(static fn (Subscription $s): ?string => $s->latestPeriod()?->ends_at->toDateString()),
                TextColumn::make('access_until')->visibleFrom('md')->label(__('subscriptions.access_until'))
                    ->state(static function (Subscription $s): ?string {
                        $latest = $s->latestPeriod();

                        return $latest === null ? null : ($s->cancelled_at !== null ? $latest->ends_at : $latest->grace_ends_at)->toDateString();
                    }),
                StatusBadgeColumn::make('state', ['active' => 'success', 'grace' => 'warning', 'cancelled' => 'gray', 'expired' => 'danger'])->label(__('subscriptions.state'))
                    ->state(static fn (Subscription $s): string => $s->displayState())
                    ->formatStateUsing(static fn (mixed $state): string => __('subscriptions.state_'.$state)),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('subscriptions.state'))->options([
                    SubscriptionStatus::Active->value => __('subscriptions.state_active'), SubscriptionStatus::Expired->value => __('subscriptions.state_expired'),
                ]),
            ])
            ->recordActions(CompactActions::group([
                Action::make('cancel')->label(__('subscriptions.cancel'))->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                    ->modalDescription(__('subscriptions.cancel_confirm'))
                    ->visible(static fn (Subscription $record): bool => Gate::allows('payment.approve_manual') && $record->cancelled_at === null && $record->status === SubscriptionStatus::Active)
                    ->action(static function (Subscription $record): void {
                        $actor = Auth::user();
                        abort_unless($actor instanceof User, 401);
                        try {
                            app(CancelSubscription::class)($actor, $record);
                        } catch (AuthorizationException) {
                            abort(403);
                        }
                        Notification::make()->success()->title(__('subscriptions.cancelled'))->send();
                    }),
            ]), CompactActions::POSITION);
    }

    public static function getPages(): array
    {
        return ['index' => ListSubscriptions::route('/')];
    }
}
