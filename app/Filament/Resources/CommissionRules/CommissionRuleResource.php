<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionRules;

use App\Domain\InstructorFinance\Actions\CloseCommissionRule;
use App\Domain\InstructorFinance\CommissionRule;
use App\Domain\InstructorFinance\SaleType;
use App\Filament\Resources\CommissionRules\Pages\ListCommissionRules;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * T-P8-09: commission rules (admin and finance panels). `ledger.view` reads; `settlement.manage` adds and closes rules through
 * SaveCommissionRule / CloseCommissionRule. Rules are never edited or deleted (ledger entries reference them).
 */
final class CommissionRuleResource extends Resource
{
    protected static ?string $model = CommissionRule::class;

    protected static ?string $slug = 'commission-rules';

    public static function getModelLabel(): string
    {
        return __('ledger.rule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ledger.rules_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-receipt-percent';
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

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['instructor', 'course']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('sale_type')->label(__('ledger.sale_type'))->badge()->state(static fn (CommissionRule $r): string => __('ledger.sale_'.$r->sale_type->value)),
                TextColumn::make('instructor')->label(__('ledger.instructor'))->state(static fn (CommissionRule $r): string => $r->instructor === null ? __('ledger.all') : (string) $r->instructor->getAttribute('name')),
                TextColumn::make('course')->label(__('ledger.course'))->state(static fn (CommissionRule $r): string => $r->course === null ? __('ledger.all') : (string) $r->course->title)->limit(40),
                TextColumn::make('rate')->label(__('ledger.rate'))->state(static fn (CommissionRule $r): string => $r->isFixed()
                    ? RefundResource::money((int) $r->fixed_amount_minor, (string) $r->currency)
                    : rtrim(rtrim(number_format(((int) $r->rate_bps) / 100, 2, '.', ''), '0'), '.').'%'),
                TextColumn::make('effective_from')->label(__('ledger.effective_from'))->date(),
                TextColumn::make('effective_to')->label(__('ledger.effective_to'))->date()->placeholder(__('ledger.open_ended')),
                StatusBadgeColumn::make('state', ['active' => 'success', 'upcoming' => 'info', 'closed' => 'gray'])->label(__('ledger.status'))
                    ->state(static fn (CommissionRule $r): string => $r->effective_from->isFuture() ? 'upcoming' : ($r->effective_to !== null && $r->effective_to->endOfDay()->isPast() ? 'closed' : 'active'))
                    ->formatStateUsing(static fn (mixed $state): string => __('ledger.rule_state_'.$state)),
                TextColumn::make('note')->label(__('ledger.note'))->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sale_type')->label(__('ledger.sale_type'))
                    ->options(collect(SaleType::cases())->mapWithKeys(static fn (SaleType $t): array => [$t->value => __('ledger.sale_'.$t->value)])->all()),
            ])
            ->recordActions([
                Action::make('close')->label(__('ledger.close_rule'))->icon('heroicon-o-lock-closed')
                    ->visible(static fn (CommissionRule $r): bool => Gate::allows('settlement.manage') && ($r->effective_to === null || $r->effective_to->endOfDay()->isFuture()))
                    ->schema([DatePicker::make('effective_to')->label(__('ledger.effective_to'))->required()])
                    ->fillForm(static fn (): array => ['effective_to' => now()->toDateString()])
                    ->action(static function (CommissionRule $record, array $data, Action $action): void {
                        $actor = Auth::user();
                        abort_unless($actor instanceof User, 401);
                        try {
                            app(CloseCommissionRule::class)($actor, $record, substr((string) $data['effective_to'], 0, 10));
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                            $action->halt();

                            return;
                        }
                        Notification::make()->success()->title(__('ledger.rule_closed'))->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCommissionRules::route('/')];
    }
}
