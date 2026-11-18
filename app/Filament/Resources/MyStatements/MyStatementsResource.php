<?php

declare(strict_types=1);

namespace App\Filament\Resources\MyStatements;

use App\Domain\InstructorFinance\InstructorStatement;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Filament\Resources\MyStatements\Pages\ListMyStatements;
use App\Filament\Resources\Statements\StatementTable;
use App\Filament\Shared\Tables\EmptyStateView;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** T-P8-10: an instructor's own frozen statements (instructor panel), read-only, with PDF download. Ownership is the only rule. */
final class MyStatementsResource extends Resource
{
    protected static ?string $model = InstructorStatement::class;

    protected static ?string $slug = 'my-statements';

    public static function getModelLabel(): string
    {
        return __('settlements.statement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlements.my_statements_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
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
        return parent::getEloquentQuery()->where('instructor_id', (int) Auth::id());
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('period', 'desc')->emptyState(EmptyStateView::make('heroicon-o-document-text', 'settlements.statements_empty_title', 'settlements.statements_empty_hint'))->columns(StatementTable::columns(false))->recordActions([StatementTable::downloadAction()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMyStatements::route('/')];
    }
}
