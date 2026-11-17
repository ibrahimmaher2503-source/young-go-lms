<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories;

use App\Domain\Shared\Search\ArabicNormalizer;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    public static function getModelLabel(): string
    {
        return __('categories.category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('categories.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-tag';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('category.view');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('category.create');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('category.update');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableTabs::make(static fn (string $locale): array => [
                TextInput::make('name.'.$locale)->label(__('categories.name'))->required()->maxLength(160),
                Textarea::make('description.'.$locale)->label(__('categories.description'))->maxLength(3000),
                TextInput::make('slug_'.$locale)->label(__('categories.slug'))->required()->maxLength(120)
                    ->regex('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u')
                    ->unique(table: Category::class, ignoreRecord: true),
            ])->columnSpanFull(),
            Select::make('parent_id')->label(__('categories.parent'))
                ->options(static fn (?Category $record): array => Category::query()->whereNull('parent_id')
                    ->when($record, static fn (Builder $query, Category $record): Builder => $query->whereKeyNot($record->getKey()))
                    ->orderBy('sort')->orderBy('id')->get()->mapWithKeys(static fn (Category $root): array => [$root->id => $root->name])->all())
                ->searchable(),
            TextInput::make('sort')->label(__('categories.sort'))->integer()->minValue(0)->maxValue(2147483647)->default(0)->required(),
            Select::make('status')->label(__('categories.status'))->options([
                'active' => __('categories.active'),
                'inactive' => __('categories.inactive'),
            ])->default('active')->required(),
            TextInput::make('icon')->label(__('categories.icon'))->maxLength(255),
            FileUpload::make('image')->label(__('categories.image'))->disk('public')->directory('categories')
                ->visibility('public')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(4096)->preventFilePathTampering(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query
                ->select('categories.*')->with('parent')
                ->leftJoin('categories as parent_categories', 'parent_categories.id', '=', 'categories.parent_id')
                ->orderByRaw('COALESCE(parent_categories.sort, categories.sort)')
                ->orderByRaw('COALESCE(categories.parent_id, categories.id)')
                ->orderByRaw('CASE WHEN categories.parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('categories.sort')->orderBy('categories.id'))
            ->columns([
                TextColumn::make('name')->label(__('categories.name'))->state(static fn (Category $record): string => $record->name)
                    ->searchable(query: static fn (Builder $query, string $search): Builder => $query->whereLike(
                        'categories.search_text',
                        '%'.ArabicNormalizer::normalize($search).'%',
                    )),
                TextColumn::make('parent.name')->label(__('categories.parent'))
                    ->state(static fn (Category $record): string => $record->parent->name ?? __('categories.root')),
                TextColumn::make('sort')->label(__('categories.sort'))->numeric(),
                TextColumn::make('status')->label(__('categories.status'))->badge()
                    ->formatStateUsing(static fn (string $state): string => __('categories.'.$state))
                    ->color(static fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(static fn (Category $record, array $data): array => [
                        ...$data,
                        'name' => $record->getTranslations('name'),
                        'description' => $record->getTranslations('description'),
                    ])
                    ->using(static fn (ListCategories $livewire, Category $record, array $data): Category => $livewire->saveCategory($data, $record)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCategories::route('/')];
    }
}
