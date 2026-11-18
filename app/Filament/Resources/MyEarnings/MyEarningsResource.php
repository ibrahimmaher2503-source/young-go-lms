<?php

declare(strict_types=1);

namespace App\Filament\Resources\MyEarnings;

use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Filament\Resources\LedgerEntries\LedgerTable;
use App\Filament\Resources\MyEarnings\Pages\ListMyEarnings;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * T-P8-09: an instructor's own earnings (instructor panel), read-only. The query is fixed to the signed-in user's own ledger
 * lines; no student identity is shown. It grants no ledger permission: ownership is the only rule.
 */
final class MyEarningsResource extends Resource
{
    protected static ?string $model = LedgerEntry::class;

    protected static ?string $slug = 'my-earnings';

    public static function getModelLabel(): string
    {
        return __('ledger.entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ledger.my_earnings_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-banknotes';
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->getAttribute('status') === 'active' && $user->hasAnyRole(PublicInstructors::ROLES);
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
        return parent::getEloquentQuery()->where('instructor_id', (int) Auth::id())->with(['course', 'order']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns(LedgerTable::columns(false))->filters(LedgerTable::filters());
    }

    public static function getPages(): array
    {
        return ['index' => ListMyEarnings::route('/')];
    }
}
