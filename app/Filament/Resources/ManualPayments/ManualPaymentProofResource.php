<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManualPayments;

use App\Domain\Commerce\Actions\ApproveManualPaymentProof;
use App\Domain\Commerce\Actions\RejectManualPaymentProof;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use App\Filament\Resources\ManualPayments\Pages\ListManualPaymentProofs;
use App\Filament\Shared\Actions\ApproveRejectAction;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * T-P8-04: finance review queue for transfer proofs (YG-GAP-PAY-004). Lists show the payer name and sender account masked;
 * the full details, the private file link and the approve/reject actions need payment.approve_manual. payment.view may read
 * the masked list. Approve and reject call the domain Actions through the shared ApproveRejectAction.
 */
final class ManualPaymentProofResource extends Resource
{
    protected static ?string $model = ManualPaymentProof::class;

    protected static ?string $slug = 'manual-payments';

    public static function getModelLabel(): string
    {
        return __('manual_payment.proof');
    }

    public static function getPluralModelLabel(): string
    {
        return __('manual_payment.queue_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-banknotes';
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
        return parent::getEloquentQuery()->with('order');
    }

    private static function money(int $minor, string $currency): string
    {
        $market = collect(Market::cases())->first(static fn (Market $m): bool => $m->currency() === $currency) ?? Market::Egypt;

        return (new PriceFormatter)->format(Money::ofMinor($minor, $currency), $market, app()->getLocale());
    }

    public static function table(Table $table): Table
    {
        $colors = ['pending' => 'info', 'approved' => 'success', 'rejected' => 'danger'];
        $canReview = static fn (): bool => Gate::allows('payment.approve_manual');

        [$approve, $reject] = ApproveRejectAction::make(
            'payment.approve_manual',
            'manual_payment_review',
            static fn (Model $record, User $actor, ?string $note) => self::guarded(static fn () => app(ApproveManualPaymentProof::class)($actor, self::proof($record), $note), 'approved_ok'),
            static fn (Model $record, User $actor, ?string $reason) => self::guarded(static fn () => app(RejectManualPaymentProof::class)($actor, self::proof($record), (string) $reason), 'rejected_ok'),
        );

        return $table->defaultSort('sla_due_at')
            ->columns([
                TextColumn::make('order.reference')->label(__('manual_payment.col_order'))->fontFamily('mono')->searchable(),
                TextColumn::make('payer')->label(__('manual_payment.col_payer'))->state(static fn (ManualPaymentProof $r): string => $r->maskedPayerName()),
                TextColumn::make('sender')->label(__('manual_payment.col_sender'))->state(static fn (ManualPaymentProof $r): string => $r->maskedSender())->fontFamily('mono'),
                TextColumn::make('transfer_reference')->label(__('manual_payment.col_reference'))->fontFamily('mono')->searchable(),
                TextColumn::make('amount_minor')->label(__('manual_payment.col_amount'))->state(static fn (ManualPaymentProof $r): string => self::money($r->amount_minor, $r->currency))
                    ->color(static fn (ManualPaymentProof $r): ?string => $r->amountMismatch() ? 'danger' : null)
                    ->description(static fn (ManualPaymentProof $r): ?string => $r->amountMismatch() ? __('manual_payment.mismatch') : null),
                TextColumn::make('expected')->label(__('manual_payment.col_expected'))->state(static fn (ManualPaymentProof $r): string => self::money((int) $r->order?->total_minor, $r->currency)),
                LocalizedDateColumn::make('transferred_at')->label(__('manual_payment.col_transferred')),
                LocalizedDateColumn::make('created_at')->label(__('manual_payment.col_submitted')),
                TextColumn::make('sla')->label(__('manual_payment.col_sla'))->badge()
                    ->state(static fn (ManualPaymentProof $r): ?string => $r->isPending() ? ($r->isOverdue() ? __('manual_payment.overdue') : $r->sla_due_at->diffForHumans()) : null)
                    ->color(static fn (ManualPaymentProof $r): string => $r->isOverdue() ? 'danger' : 'gray'),
                StatusBadgeColumn::make('status', $colors)->label(__('manual_payment.col_status'))
                    ->formatStateUsing(static fn (mixed $state): string => __('manual_payment.state_'.($state instanceof ManualPaymentStatus ? $state->value : (string) $state))),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('manual_payment.filter_status'))->default(ManualPaymentStatus::Pending->value)
                    ->options(collect(ManualPaymentStatus::cases())->mapWithKeys(static fn (ManualPaymentStatus $s): array => [$s->value => __('manual_payment.state_'.$s->value)])->all()),
            ])
            ->recordActions([
                Action::make('details')->label(__('manual_payment.details'))->icon('heroicon-o-eye')->visible($canReview)
                    ->modalHeading(__('manual_payment.proof'))->modalSubmitAction(false)
                    ->modalContent(static fn (ManualPaymentProof $record): HtmlString => self::details($record)),
                Action::make('file')->label(__('manual_payment.view_file'))->icon('heroicon-o-document-magnifying-glass')->visible($canReview)
                    ->url(static fn (ManualPaymentProof $record): string => route('manual-payments.file', $record), shouldOpenInNewTab: true),
                $approve->visible(static fn (ManualPaymentProof $r): bool => $r->isPending() && $canReview()),
                $reject->visible(static fn (ManualPaymentProof $r): bool => $r->isPending() && $canReview()),
            ]);
    }

    private static function proof(Model $record): ManualPaymentProof
    {
        abort_unless($record instanceof ManualPaymentProof, 404);

        return $record;
    }

    /** Runs a domain call; a refusal shows as a danger notification and halts the action (the transaction rolls back). */
    private static function guarded(Closure $call, string $successKey): void
    {
        try {
            $call();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            throw new Halt;
        }
        Notification::make()->success()->title(__('manual_payment.'.$successKey))->send();
    }

    private static function details(ManualPaymentProof $r): HtmlString
    {
        $rows = [
            __('manual_payment.col_payer') => $r->payer_name,
            __('manual_payment.col_sender') => $r->sender_account,
            __('manual_payment.col_reference') => $r->transfer_reference,
            __('manual_payment.col_amount') => self::money($r->amount_minor, $r->currency),
            __('manual_payment.col_expected') => self::money((int) $r->order?->total_minor, $r->currency),
        ];
        $html = '<dl class="space-y-2">';
        foreach ($rows as $label => $value) {
            $html .= '<div><dt class="text-sm text-gray-500">'.e($label).'</dt><dd class="font-semibold" dir="auto">'.e($value).'</dd></div>';
        }

        return new HtmlString($html.'</dl>');
    }

    public static function getPages(): array
    {
        return ['index' => ListManualPaymentProofs::route('/')];
    }
}
