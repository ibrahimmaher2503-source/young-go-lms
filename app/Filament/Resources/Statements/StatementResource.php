<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statements;

use App\Domain\InstructorFinance\InstructorStatement;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Filament\Resources\Statements\Pages\ListStatements;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * T-P8-10: frozen monthly instructor statements for finance and admin (`ledger.view`). Read-only; generating (`settlement.manage`)
 * is a header action that calls GenerateMonthlyStatements. The PDF goes through the statements.pdf route.
 */
final class StatementResource extends Resource
{
    protected static ?string $model = InstructorStatement::class;

    protected static ?string $slug = 'instructor-statements';

    public static function getModelLabel(): string
    {
        return __('settlements.statement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlements.statements_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
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
        return parent::getEloquentQuery()->with('instructor');
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns(StatementTable::columns(true))
            ->filters([
                SelectFilter::make('instructor_id')->label(__('ledger.instructor'))->searchable()
                    ->options(static fn (): array => User::query()->role(PublicInstructors::ROLES)->orderBy('name')->limit(300)->pluck('name', 'id')->all()),
                SelectFilter::make('period')->label(__('settlements.period'))
                    ->options(static fn (): array => InstructorStatement::query()->select('period')->distinct()->orderByDesc('period')->limit(36)->pluck('period', 'period')->all()),
            ])
            ->recordActions([StatementTable::downloadAction()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStatements::route('/')];
    }
}
