<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlogTags;

use App\Domain\Content\Actions\SaveBlogTag;
use App\Filament\Resources\BlogTags\Pages\ListBlogTags;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Models\BlogTag;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class BlogTagResource extends Resource
{
    protected static ?string $model = BlogTag::class;

    public static function getModelLabel(): string
    {
        return __('blog.tag');
    }

    public static function getPluralModelLabel(): string
    {
        return __('blog.tag_plural');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-hashtag';
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
                TextInput::make('name.'.$locale)->label(__('blog.name'))->required()->maxLength(80),
                TextInput::make('slug_'.$locale)->label(__('blog.slug'))->required()->maxLength(120)
                    ->regex('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u')->unique(table: BlogTag::class, ignoreRecord: true),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id')->columns([
            TextColumn::make('name')->label(__('blog.name'))->state(static fn (BlogTag $record): string => $record->name),
            TextColumn::make('slug_en')->label(__('blog.slug')),
        ])->recordActions([
            EditAction::make()
                ->mutateRecordDataUsing(static fn (BlogTag $record, array $data): array => [...$data, ...$record->getTranslations()])
                ->using(static function (BlogTag $record, array $data): BlogTag {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 403);

                    return app(SaveBlogTag::class)($actor, $record, $data);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBlogTags::route('/')];
    }
}
