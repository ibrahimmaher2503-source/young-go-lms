<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders;

use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\Queries\OrderDirectory;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Shared\Tables\CompactActions;
use App\Filament\Shared\Tables\EmptyStateView;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * @extends resource<Order>
 *
 * Staff orders list (read only, permission payment.view): reference, learner/payer, amount, gateway and status, with the
 * order lines in a view dialog. Refunds, manual proofs and reconciliation keep their own queues; nothing here edits an order.
 */
final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'orders';

    public static function getModelLabel(): string
    {
        return __('orders.order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('orders.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shopping-bag';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('payment.view');
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

    /** @return Builder<Order> */
    public static function getEloquentQuery(): Builder
    {
        return OrderDirectory::query();
    }

    private static function gatewayLabel(Order $order): string
    {
        $label = static fn (string $key): string => trans()->has('orders.gateway_'.$key) ? (string) __('orders.gateway_'.$key) : ucfirst($key);
        $manual = (string) __('orders.gateway_manual');

        // The gateway that fulfilled the order wins; a transfer proof means bank transfer. A paid order with neither is a manual
        // payment (the receipt makes the same call); an open or failed order shows the gateway of its latest attempt, else a dash.
        if ($order->getAttribute('paid_gateway') !== null) {
            return $label((string) $order->getAttribute('paid_gateway'));
        }
        if ($order->getAttribute('has_manual_proof') !== null) {
            return $manual;
        }
        if (in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded, OrderStatus::PartiallyRefunded, OrderStatus::Disputed], true)) {
            return $manual;
        }

        return $order->getAttribute('last_gateway') !== null ? $label((string) $order->getAttribute('last_gateway')) : '—';
    }

    public static function table(Table $table): Table
    {
        $colors = ['pending' => 'warning', 'paid' => 'success', 'failed' => 'danger', 'cancelled' => 'gray', 'refunded' => 'gray', 'partially_refunded' => 'info', 'disputed' => 'danger'];

        return $table->extraAttributes(['class' => 'yg-compact-table'], merge: true)->defaultSort('id', 'desc')
            ->emptyState(EmptyStateView::make('heroicon-o-shopping-bag', 'orders.empty_title', 'orders.empty_hint'))
            ->columns([
                TextColumn::make('reference')->label(__('orders.col_reference'))->fontFamily('mono')->searchable(),
                TextColumn::make('payer.display_name')->label(__('orders.col_learner'))->searchable(),
                TextColumn::make('total_minor')->label(__('orders.col_amount'))->state(static fn (Order $o): string => RefundResource::money($o->total_minor, $o->currency)),
                TextColumn::make('last_gateway')->visibleFrom('md')->label(__('orders.col_gateway'))->badge()->state(static fn (Order $o): string => self::gatewayLabel($o)),
                LocalizedDateColumn::make('created_at')->label(__('orders.col_created'))->visibleFrom('md'),
                StatusBadgeColumn::make('status', $colors)->label(__('orders.col_status'))
                    ->formatStateUsing(static fn (mixed $state): string => __('refunds.order_status_'.($state instanceof OrderStatus ? $state->value : (string) $state))),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('orders.filter_status'))
                    ->options(collect(OrderStatus::cases())->mapWithKeys(static fn (OrderStatus $s): array => [$s->value => __('refunds.order_status_'.$s->value)])->all()),
            ])
            ->recordActions(CompactActions::group([
                ViewAction::make()->label(__('orders.view'))->modalHeading(static fn (Order $o): string => __('orders.order').' '.$o->reference)
                    ->schema(static fn (Order $o): array => [
                        TextEntry::make('reference')->label(__('orders.col_reference'))->fontFamily('mono'),
                        TextEntry::make('payer.display_name')->label(__('orders.col_learner')),
                        TextEntry::make('total')->label(__('orders.col_amount'))->state(RefundResource::money($o->total_minor, $o->currency)),
                        TextEntry::make('lines')->label(__('orders.lines'))->listWithLineBreaks()
                            ->state($o->items()->get()->map(static fn ($i): string => (string) (app()->getLocale() === 'ar' ? ($i->title_ar ?? $i->title_en) : ($i->title_en ?? $i->title_ar)) ?: '#'.$i->product_id)->all()),
                    ]),
            ]), CompactActions::POSITION);
    }

    public static function getPages(): array
    {
        return ['index' => ListOrders::route('/')];
    }
}
