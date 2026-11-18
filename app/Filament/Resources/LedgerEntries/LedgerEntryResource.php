<?php

declare(strict_types=1);

namespace App\Filament\Resources\LedgerEntries;

use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Filament\Resources\LedgerEntries\Pages\ListLedgerEntries;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

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
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLedgerEntries::route('/')];
    }
}
