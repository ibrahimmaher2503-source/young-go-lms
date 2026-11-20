<?php

declare(strict_types=1);

namespace App\Filament\Resources\Refunds;

use App\Domain\Commerce\Actions\ApproveRefund;
use App\Domain\Commerce\Actions\ExecuteRefund;
use App\Domain\Commerce\Actions\RejectRefund;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundMethod;
use App\Domain\Commerce\RefundStatus;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use App\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Filament\Shared\Actions\ApproveRejectAction;
use App\Filament\Shared\HasQueueBadge;
use App\Filament\Shared\Tables\CompactActions;
use App\Filament\Shared\Tables\EmptyStateView;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * T-P8-06: finance refund queue (PRD 17.3). Request (list header), approve/reject (shared ApproveRejectAction, ability
 * payment.refund) and execute run the domain Actions; payment.view may read the list. The original order and payment rows
 * are never edited here.
 */
final class RefundResource extends Resource
{
    use HasQueueBadge;

    protected static ?string $model = Refund::class;

    protected static ?string $slug = 'refunds';

    public static function getModelLabel(): string
    {
        return __('refunds.refund');
    }

    public static function getPluralModelLabel(): string
    {
        return __('refunds.queue_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-arrow-uturn-left';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('payment.view') || Gate::allows('payment.refund');
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
        return parent::getEloquentQuery()->with(['order.payer', 'requester']);
    }

    public static function money(int $minor, string $currency): string
    {
        $market = collect(Market::cases())->first(static fn (Market $m): bool => $m->currency() === $currency) ?? Market::Egypt;

        $formatter = new PriceFormatter;
        $money = Money::ofMinor($minor, $currency);

        // Negative amounts (reversals, debit adjustments) render as one LTR-isolated run so the sign stays next to the number in Arabic.
        return $minor < 0
            ? $formatter->formatDeduction($money, $market, app()->getLocale())
            : $formatter->format($money, $market, app()->getLocale());
    }

    public static function currencyLabel(string $currency): string
    {
        return app()->isLocale('ar') ? match ($currency) {
            'EGP' => 'ج.م.',
            'SAR' => 'ر.س.',
            default => $currency,
        } : $currency;
    }

    public static function table(Table $table): Table
    {
        $colors = ['requested' => 'info', 'approved' => 'warning', 'processing' => 'warning', 'executed' => 'success', 'rejected' => 'gray', 'failed' => 'danger'];
        $canRefund = static fn (): bool => Gate::allows('payment.refund');

        [$approve, $reject] = ApproveRejectAction::make(
            'payment.refund',
            'refund_review',
            static fn (Model $record, User $actor, ?string $note) => self::guarded(static fn () => app(ApproveRefund::class)($actor, self::refund($record), $note), 'approved_ok'),
            static fn (Model $record, User $actor, ?string $reason) => self::guarded(static fn () => app(RejectRefund::class)($actor, self::refund($record), (string) $reason), 'rejected_ok'),
        );

        $execute = Action::make('execute')->label(__('refunds.execute'))->icon('heroicon-o-banknotes')->color('warning')
            ->visible(static fn (Refund $r): bool => $r->status === RefundStatus::Approved && $canRefund())
            ->modalHeading(__('refunds.execute_heading'))->modalDescription(static fn (Refund $r): string => $r->method === RefundMethod::Gateway ? __('refunds.execute_gateway_help') : __('refunds.execute_manual_help'))
            ->schema(static fn (Refund $r): array => $r->method === RefundMethod::Manual
                ? [TextInput::make('external_reference')->label(__('refunds.external_reference'))->required()->minLength(3)->maxLength(64)]
                : [])
            ->requiresConfirmation()
            ->action(static function (Refund $record, array $data): void {
                $actor = Auth::user();
                abort_unless($actor instanceof User, 403);
                $reference = $data['external_reference'] ?? null;
                self::guarded(static fn () => app(ExecuteRefund::class)($actor, $record, is_string($reference) ? $reference : null), 'executed_ok');
            });

        return $table->extraAttributes(['class' => 'yg-compact-table'], merge: true)->defaultSort('id', 'desc')
            ->emptyState(EmptyStateView::make('heroicon-o-arrow-uturn-left', 'refunds.queue_empty_title', 'refunds.queue_empty_hint'))
            ->columns([
                TextColumn::make('reference')->visibleFrom('md')->label(__('refunds.col_reference'))->fontFamily('mono')->searchable(),
                TextColumn::make('order.reference')->visibleFrom('md')->label(__('refunds.col_order'))->fontFamily('mono')->searchable(),
                TextColumn::make('payer')->label(__('refunds.col_payer'))->state(static fn (Refund $r): string => (string) $r->order?->payer?->getAttribute('display_name')),
                TextColumn::make('amount_minor')->label(__('refunds.col_amount'))->state(static fn (Refund $r): string => self::money($r->amount_minor, $r->currency)),
                TextColumn::make('method')->visibleFrom('md')->label(__('refunds.col_method'))->badge()->state(static fn (Refund $r): string => __('refunds.method_'.$r->method->value)),
                TextColumn::make('reason')->label(__('refunds.col_reason'))->visibleFrom('md')->toggleable(isToggledHiddenByDefault: true)->limit(60)->wrap()->extraAttributes(['dir' => 'auto'])->tooltip(static fn (Refund $r): string => $r->reason),
                TextColumn::make('requested_by')->label(__('refunds.col_requested_by'))->visibleFrom('md')->toggleable(isToggledHiddenByDefault: true)->state(static fn (Refund $r): string => (string) $r->requester?->getAttribute('display_name')),
                LocalizedDateColumn::make('created_at')->label(__('refunds.col_requested'))->visibleFrom('md')->toggleable(isToggledHiddenByDefault: true),
                StatusBadgeColumn::make('status', $colors)->label(__('refunds.col_status'))
                    ->formatStateUsing(static fn (mixed $state): string => __('refunds.state_'.($state instanceof RefundStatus ? $state->value : (string) $state))),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('refunds.filter_status'))
                    ->options(collect(RefundStatus::cases())->mapWithKeys(static fn (RefundStatus $s): array => [$s->value => __('refunds.state_'.$s->value)])->all()),
            ])
            ->recordActions(CompactActions::group([
                $approve->visible(static fn (Refund $r): bool => $r->status === RefundStatus::Requested && $canRefund()),
                $reject->visible(static fn (Refund $r): bool => in_array($r->status, [RefundStatus::Requested, RefundStatus::Approved], true) && $canRefund()),
                $execute,
            ]), CompactActions::POSITION);
    }

    private static function refund(Model $record): Refund
    {
        abort_unless($record instanceof Refund, 404);

        return $record;
    }

    /** Runs a domain call; a refusal shows as a danger notification and halts the action (the transaction rolls back). */
    public static function guarded(Closure $call, string $successKey): void
    {
        try {
            $call();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            throw new Halt;
        }
        Notification::make()->success()->title(__('refunds.'.$successKey))->send();
    }

    protected static function queueCount(): int
    {
        return self::getEloquentQuery()->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Approved->value])->count();
    }

    public static function getPages(): array
    {
        return ['index' => ListRefunds::route('/')];
    }
}
