<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomepageBlocks;

use App\Domain\Content\Actions\SaveCmsContent;
use App\Filament\Resources\HomepageBlocks\Pages\ListHomepageBlocks;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Models\HomepageBlock;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class HomepageBlockResource extends Resource
{
    protected static ?string $model = HomepageBlock::class;

    public static function getModelLabel(): string
    {
        return __('cms.block');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cms.blocks');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('content.page.manage');
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
            Select::make('type')->label(__('cms.type'))
                ->options(array_combine(HomepageBlock::TYPES, array_map(static fn (string $type): string => __('cms.'.$type), HomepageBlock::TYPES)))
                ->required(),
            TextInput::make('sort')->label(__('cms.sort'))->integer()->minValue(0)->maxValue(2147483647)->default(0)->required(),
            Toggle::make('visible')->label(__('cms.visible'))->default(false),
            TranslatableTabs::make(static fn (string $locale): array => [
                TextInput::make('title.'.$locale)->label(__('cms.title'))->required()->maxLength(160),
                Textarea::make('body.'.$locale)->label(__('cms.body'))->rows(5)->maxLength(10000),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->columns([
            TextColumn::make('title')->label(__('cms.title'))->state(static fn (HomepageBlock $record): string => $record->title),
            TextColumn::make('type')->label(__('cms.type'))->formatStateUsing(static fn (string $state): string => __('cms.'.$state)),
            TextColumn::make('sort')->label(__('cms.sort'))->numeric(),
            IconColumn::make('visible')->label(__('cms.visible'))->boolean(),
        ])->recordActions([
            EditAction::make()
                ->mutateRecordDataUsing(static fn (HomepageBlock $record, array $data): array => [...$data, ...$record->getTranslations()])
                ->using(static fn (HomepageBlock $record, array $data): HomepageBlock => app(SaveCmsContent::class)($record, $data)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListHomepageBlocks::route('/')];
    }
}
