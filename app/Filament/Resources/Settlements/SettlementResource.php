<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settlements;

use App\Domain\InstructorFinance\Actions\ApproveSettlement;
use App\Domain\InstructorFinance\Actions\CancelSettlement;
use App\Domain\InstructorFinance\Actions\RecordSettlementPayout;
use App\Domain\InstructorFinance\PayoutMethod;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\InstructorFinance\SettlementItem;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Resources\Settlements\Pages\ListSettlements;
use App\Filament\Shared\Tables\EmptyStateView;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * T-P8-10: settlement batches for finance and admin. Read with `ledger.view`; create (list page), approve, record a payout and
 * cancel need `settlement.manage` and go through the settlement Actions. Settlement is a record of an offline payout only.
 */
final class SettlementResource extends Resource
{
    protected static ?string $model = Settlement::class;

    protected static ?string $slug = 'settlements';

    public static function getModelLabel(): string
    {
        return __('settlements.settlement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlements.settlements_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-banknotes';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('ledger.view') || Gate::allows('settlement.manage');
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

    public static function table(Table $table): Table
    {
        $manage = static fn (): bool => Gate::allows('settlement.manage');
        $colors = ['draft' => 'info', 'approved' => 'warning', 'paid' => 'success', 'cancelled' => 'gray'];

        return $table->defaultSort('id', 'desc')
            ->emptyState(EmptyStateView::make('heroicon-o-clipboard-document-check', 'settlements.settlements_empty_title', 'settlements.settlements_empty_hint'))
            ->columns([
                TextColumn::make('reference')->label(__('settlements.reference'))->fontFamily('mono')->searchable(),
                TextColumn::make('instructor_count')->label(__('settlements.instructors')),
                TextColumn::make('entry_count')->label(__('settlements.entries')),
                TextColumn::make('total_minor')->label(__('settlements.total'))->state(static fn (Settlement $s): string => RefundResource::money($s->total_minor, $s->currency))->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('creator')->label(__('settlements.created_by'))->state(static fn (Settlement $s): string => (string) $s->creator?->getAttribute('display_name')),
                LocalizedDateColumn::make('created_at')->label(__('settlements.created'))->sortable(),
                StatusBadgeColumn::make('status', $colors)->label(__('ledger.status'))
                    ->formatStateUsing(static fn (mixed $state): string => __('settlements.status_'.($state instanceof SettlementStatus ? $state->value : (string) $state))),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('ledger.status'))
                    ->options(collect(SettlementStatus::cases())->mapWithKeys(static fn (SettlementStatus $s): array => [$s->value => __('settlements.status_'.$s->value)])->all()),
            ])
            ->recordActions([
                Action::make('details')->label(__('settlements.details'))->icon('heroicon-o-eye')->modalSubmitAction(false)->modalCancelActionLabel(__('settlements.close'))
                    ->modalHeading(static fn (Settlement $s): string => $s->reference)
                    ->modalContent(static fn (Settlement $s) => view('filament.settlements.details', ['settlement' => $s->load('items.instructor')])),
                Action::make('approve')->label(__('settlements.approve'))->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()
                    ->modalDescription(__('settlements.approve_help'))
                    ->visible(static fn (Settlement $s): bool => $s->status === SettlementStatus::Draft && $manage())
                    ->action(static fn (Settlement $s) => self::guarded(static fn (User $u) => app(ApproveSettlement::class)($u, $s), 'approved_ok')),
                Action::make('payout')->label(__('settlements.record_payout'))->icon('heroicon-o-banknotes')->color('warning')
                    ->visible(static fn (Settlement $s): bool => $s->status === SettlementStatus::Approved && $manage())
                    ->modalDescription(__('settlements.payout_help'))
                    ->schema(static fn (Settlement $s): array => [
                        Select::make('item_id')->label(__('ledger.instructor'))->required()->native(false)
                            ->options($s->items()->whereNull('paid_at')->with('instructor')->get()->mapWithKeys(static fn (SettlementItem $i): array => [$i->getKey() => $i->instructor?->getAttribute('display_name').' - '.RefundResource::money($i->amount_minor, $s->currency)])->all()),
                        Select::make('method')->label(__('settlements.method'))->required()->native(false)
                            ->options(collect(PayoutMethod::allowedFor($s->currency))->mapWithKeys(static fn (PayoutMethod $m): array => [$m->value => __('settlements.method_'.$m->value)])->all())
                            ->helperText(__('settlements.method_help_'.strtolower($s->currency))),
                        TextInput::make('reference')->label(__('settlements.payout_reference'))->required()->minLength(3)->maxLength(64)->helperText(__('settlements.reference_help')),
                        DatePicker::make('paid_on')->label(__('settlements.paid_on'))->required()->maxDate(now())->default(now()),
                    ])
                    ->action(static function (Settlement $s, array $data): void {
                        $item = SettlementItem::query()->where('settlement_id', $s->getKey())->findOrFail((int) $data['item_id']);
                        self::guarded(static fn (User $u) => app(RecordSettlementPayout::class)($u, $item, PayoutMethod::tryFrom((string) $data['method']) ?? throw ValidationException::withMessages(['method' => __('settlements.method_not_allowed')]), (string) $data['reference'], (string) $data['paid_on']), 'payout_ok');
                    }),
                Action::make('cancel')->label(__('settlements.cancel'))->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(static fn (Settlement $s): bool => in_array($s->status, [SettlementStatus::Draft, SettlementStatus::Approved], true) && $manage())
                    ->modalDescription(__('settlements.cancel_help'))
                    ->schema([Textarea::make('reason')->label(__('ledger.reason'))->required()->minLength(5)->maxLength(500)])
                    ->action(static fn (Settlement $s, array $data) => self::guarded(static fn (User $u) => app(CancelSettlement::class)($u, $s, (string) $data['reason']), 'cancelled_ok')),
            ]);
    }

    /** @param  Closure(User): mixed  $run */
    public static function guarded(Closure $run, string $doneKey): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);

        try {
            $run($actor);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            throw new Halt;
        }
        Notification::make()->success()->title(__('settlements.'.$doneKey))->send();
    }

    public static function getPages(): array
    {
        return ['index' => ListSettlements::route('/')];
    }
}
