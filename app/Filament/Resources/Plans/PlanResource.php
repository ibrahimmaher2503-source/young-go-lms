<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plans;

use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use Brick\Money\Money as BrickMoney;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * T-P8-08: staff list of subscription plans (admin and finance panels). Finance and admin (payment.approve_manual) manage
 * through the shared modal and SavePlan; payment.view may read.
 */
final class PlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $slug = 'subscription-plans';

    public static function getModelLabel(): string
    {
        return __('subscriptions.plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('subscriptions.plans_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-rectangle-stack';
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
        return parent::getEloquentQuery()->withCount('courses')->with('prices');
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('subscriptions.plan_name'))->state(static fn (SubscriptionPlan $p): string => (string) $p->name)
                    ->searchable(query: static fn (Builder $query, string $search): Builder => $query->whereLike('name', '%'.$search.'%')),
                TextColumn::make('term')->label(__('subscriptions.term'))->state(static fn (SubscriptionPlan $p): string => __('subscriptions.term_'.$p->term->value)),
                TextColumn::make('courses_count')->label(__('subscriptions.courses')),
                TextColumn::make('price_egp')->label(__('subscriptions.price_column'))->state(static function (SubscriptionPlan $p): string {
                    $price = $p->priceFor(Market::Egypt);

                    return $price === null ? '-' : BrickMoney::ofMinor($price->amount_minor, $price->currency)->formatToLocale('en-US');
                }),
                StatusBadgeColumn::make('is_active', ['active' => 'success', 'inactive' => 'gray'])->label(__('subscriptions.status'))
                    ->state(static fn (SubscriptionPlan $p): string => $p->is_active ? 'active' : 'inactive')
                    ->formatStateUsing(static fn (mixed $state): string => __('subscriptions.state_'.$state)),
            ])
            ->filters([TernaryFilter::make('is_active')->label(__('subscriptions.active'))])
            ->recordActions([
                PlanForm::configure(Action::make('edit')->label(__('subscriptions.edit'))->icon('heroicon-o-pencil-square')
                    ->visible(static fn (): bool => Gate::allows('payment.approve_manual'))
                    ->modalHeading(__('subscriptions.edit'))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPlans::route('/')];
    }
}
