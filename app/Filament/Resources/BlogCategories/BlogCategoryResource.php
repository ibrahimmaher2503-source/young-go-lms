<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlogCategories;

use App\Domain\Content\Actions\SaveBlogCategory;
use App\Filament\Resources\BlogCategories\Pages\ListBlogCategories;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Models\BlogCategory;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class BlogCategoryResource extends Resource
{
    protected static ?string $model = BlogCategory::class;

    public static function getModelLabel(): string
    {
        return __('blog.category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('blog.category_plural');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-rectangle-stack';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('content.blog.manage');
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableTabs::make(static fn (string $locale): array => [
                TextInput::make('name.'.$locale)->label(__('blog.name'))->required()->maxLength(160),
                Textarea::make('description.'.$locale)->label(__('blog.description_field'))->maxLength(3000),
                TextInput::make('slug_'.$locale)->label(__('blog.slug'))->required()->maxLength(120)
                    ->regex('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u')->unique(table: BlogCategory::class, ignoreRecord: true),
            ])->columnSpanFull(),
            TextInput::make('sort')->label(__('blog.sort'))->integer()->minValue(0)->maxValue(2147483647)->default(0)->required(),
            Select::make('status')->label(__('blog.status'))->options([
                BlogCategory::ACTIVE => __('blog.active'),
                BlogCategory::INACTIVE => __('blog.inactive'),
            ])->default(BlogCategory::ACTIVE)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->columns([
            TextColumn::make('name')->label(__('blog.name'))->state(static fn (BlogCategory $record): string => $record->name),
            TextColumn::make('slug_en')->label(__('blog.slug')),
            TextColumn::make('sort')->label(__('blog.sort'))->numeric(),
            TextColumn::make('status')->label(__('blog.status'))->badge()
                ->formatStateUsing(static fn (string $state): string => __('blog.'.$state))
                ->color(static fn (string $state): string => $state === BlogCategory::ACTIVE ? 'success' : 'gray'),
        ])->recordActions([
            EditAction::make()
                ->mutateRecordDataUsing(static fn (BlogCategory $record, array $data): array => [...$data, ...$record->getTranslations()])
                ->using(static function (BlogCategory $record, array $data): BlogCategory {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 403);

                    return app(SaveBlogCategory::class)($actor, $record, $data);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBlogCategories::route('/')];
    }
}
