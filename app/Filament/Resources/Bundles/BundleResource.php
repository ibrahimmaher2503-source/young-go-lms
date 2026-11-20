<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bundles;

use App\Domain\Commerce\Actions\AllocateBundlePrice;
use App\Domain\Commerce\Bundle;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\Bundles\Pages\ListBundles;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Shared\Tables\CompactActions;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * T-P8-07: staff list of bundles (admin and finance panels). Finance and admin (payment.approve_manual) manage through
 * the shared modal and SaveBundle; payment.view may read. The "allocation" action previews how the Egypt price is split
 * across the member courses (the same AllocateBundlePrice the order engine uses).
 */
final class BundleResource extends Resource
{
    protected static ?string $model = Bundle::class;

    protected static ?string $slug = 'bundles';

    public static function getModelLabel(): string
    {
        return __('bundles.bundle');
    }

    public static function getPluralModelLabel(): string
    {
        return __('bundles.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-squares-2x2';
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
        return parent::getEloquentQuery()->withCount('items')->with('prices');
    }

    public static function table(Table $table): Table
    {
        return $table->extraAttributes(['class' => 'yg-compact-table'], merge: true)->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('bundles.name'))->state(static fn (Bundle $b): string => (string) $b->name)
                    ->searchable(query: static fn (Builder $query, string $search): Builder => $query->whereLike('name', '%'.$search.'%')),
                TextColumn::make('items_count')->visibleFrom('md')->label(__('bundles.courses')),
                TextColumn::make('price_egp')->label(__('bundles.price_column'))->state(static function (Bundle $b): string {
                    $price = $b->priceFor(Market::Egypt);

                    return $price === null ? '-' : RefundResource::money($price->amount_minor, $price->currency);
                }),
                StatusBadgeColumn::make('is_active', ['active' => 'success', 'inactive' => 'gray'])->label(__('bundles.status'))
                    ->state(static fn (Bundle $b): string => $b->is_active ? 'active' : 'inactive')
                    ->formatStateUsing(static fn (mixed $state): string => __('bundles.state_'.$state)),
            ])
            ->filters([TernaryFilter::make('is_active')->label(__('bundles.active'))])
            ->recordActions(CompactActions::group([
                Action::make('allocation')->label(__('bundles.allocation'))->icon('heroicon-o-calculator')->modalSubmitAction(false)
                    ->modalHeading(__('bundles.allocation'))->modalContent(static fn (Bundle $record): HtmlString => self::allocationPreview($record)),
                BundleForm::configure(Action::make('edit')->label(__('bundles.edit'))->icon('heroicon-o-pencil-square')
                    ->visible(static fn (): bool => Gate::allows('payment.approve_manual'))
                    ->modalHeading(__('bundles.edit'))),
            ]), CompactActions::POSITION);
    }

    public static function getPages(): array
    {
        return ['index' => ListBundles::route('/')];
    }

    private static function allocationPreview(Bundle $bundle): HtmlString
    {
        $price = $bundle->priceFor(Market::Egypt);
        if ($price === null) {
            return new HtmlString('<p>'.e(__('bundles.no_price')).'</p>');
        }
        $titles = $bundle->items()->with('course')->get()->mapWithKeys(static fn ($i): array => [$i->course_id => (string) $i->course?->title]);
        $rows = '';
        foreach (app(AllocateBundlePrice::class)($bundle, Market::Egypt, $price->amount_minor) as $courseId => $share) {
            $rows .= '<tr><td>'.e((string) ($titles[$courseId] ?? $courseId)).'</td><td>'.e((string) $share['weight']).'</td><td>'
                .e(RefundResource::money($share['allocated_minor'], $price->currency)).'</td></tr>';
        }

        return new HtmlString('<table class="w-full text-start"><thead><tr><th>'.e(__('bundles.course')).'</th><th>'.e(__('bundles.weight')).'</th><th>'
            .e(__('bundles.allocated')).'</th></tr></thead><tbody>'.$rows.'</tbody></table>');
    }
}
