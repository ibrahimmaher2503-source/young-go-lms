<?php

declare(strict_types=1);

namespace App\Filament\Resources\LedgerEntries;

use App\Domain\InstructorFinance\Actions\CreateSettlementBatch;
use App\Domain\InstructorFinance\Actions\HoldLedgerEntries;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Filament\Resources\LedgerEntries\Pages\ListLedgerEntries;
use App\Models\User;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * T-P8-09: the instructor ledger for finance and admin (`ledger.view`). Read-only: lines are never edited or deleted. Manual
 * adjustments (`settlement.manage`) are a header action on the list page and go through RecordLedgerAdjustment.
 */
final class LedgerEntryResource extends Resource
{
    protected static ?string $model = LedgerEntry::class;

    protected static ?string $slug = 'instructor-ledger';

    public static function getModelLabel(): string
    {
        return __('ledger.entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ledger.ledger_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-book-open';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('ledger.view');
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
        return parent::getEloquentQuery()->with(['instructor', 'course', 'order']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns(LedgerTable::columns(true))
            ->filters([
                SelectFilter::make('instructor_id')->label(__('ledger.instructor'))->searchable()
                    ->options(static fn (): array => User::query()->role(PublicInstructors::ROLES)->orderBy('name')->limit(300)->pluck('name', 'id')->all()),
                ...LedgerTable::filters(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                // T-P8-10: settlement workflow on the ledger. Status only; amounts are never touched.
                BulkAction::make('add_to_batch')->label(__('settlements.add_to_batch'))->icon('heroicon-o-banknotes')->deselectRecordsAfterCompletion()
                    ->visible(static fn (): bool => Gate::allows('settlement.manage'))
                    ->modalDescription(__('settlements.add_to_batch_help'))
                    ->schema([Textarea::make('note')->label(__('ledger.note'))->maxLength(255)->rows(2)])
                    ->action(static fn (Collection $records, array $data) => self::guarded(static function (User $actor) use ($records, $data): void {
                        $currencies = $records->pluck('currency')->unique();
                        if ($currencies->count() !== 1) {
                            throw ValidationException::withMessages(['entries' => __('settlements.entries_invalid')]);
                        }
                        $settlement = app(CreateSettlementBatch::class)($actor, (string) $currencies->first(), $records->pluck('id')->map(static fn ($id): int => (int) $id)->all(), filled($data['note'] ?? null) ? (string) $data['note'] : null);
                        Notification::make()->success()->title(__('settlements.created_ok', ['reference' => $settlement->reference]))->send();
                    })),
                BulkAction::make('hold')->label(__('settlements.hold'))->icon('heroicon-o-pause-circle')->color('danger')->deselectRecordsAfterCompletion()
                    ->visible(static fn (): bool => Gate::allows('settlement.manage'))
                    ->modalDescription(__('settlements.hold_help'))
                    ->schema([Textarea::make('reason')->label(__('ledger.reason'))->required()->minLength(5)->maxLength(500)])
                    ->action(static fn (Collection $records, array $data) => self::guarded(static function (User $actor) use ($records, $data): void {
                        $changed = app(HoldLedgerEntries::class)->hold($actor, $records->pluck('id')->map(static fn ($id): int => (int) $id)->all(), (string) $data['reason']);
                        Notification::make()->success()->title(__('settlements.held_ok', ['count' => $changed]))->send();
                    })),
                BulkAction::make('release')->label(__('settlements.release'))->icon('heroicon-o-play-circle')->deselectRecordsAfterCompletion()
                    ->visible(static fn (): bool => Gate::allows('settlement.manage'))
                    ->schema([Textarea::make('reason')->label(__('ledger.reason'))->required()->minLength(5)->maxLength(500)])
                    ->action(static fn (Collection $records, array $data) => self::guarded(static function (User $actor) use ($records, $data): void {
                        $changed = app(HoldLedgerEntries::class)->release($actor, $records->pluck('id')->map(static fn ($id): int => (int) $id)->all(), (string) $data['reason']);
                        Notification::make()->success()->title(__('settlements.released_ok', ['count' => $changed]))->send();
                    })),
            ])]);
    }

    /** @param  Closure(User): void  $run */
    private static function guarded(Closure $run): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);

        try {
            $run($actor);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => ListLedgerEntries::route('/')];
    }
}
