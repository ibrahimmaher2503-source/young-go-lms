<?php

declare(strict_types=1);

namespace App\Filament\Resources\CmsPages;

use App\Domain\Content\Actions\SaveCmsContent;
use App\Filament\Resources\CmsPages\Pages\ListCmsPages;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Filament\Shared\Tables\CompactActions;
use App\Models\CmsPage;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

final class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;

    public static function getModelLabel(): string
    {
        return __('cms.page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cms.pages');
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
            TextInput::make('slug')->label(__('cms.slug'))->required()->maxLength(120)
                ->regex('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u')->unique(table: CmsPage::class, ignoreRecord: true),
            Select::make('status')->label(__('cms.status'))->options([
                CmsPage::DRAFT => __('cms.draft'),
                CmsPage::PUBLISHED => __('cms.published'),
            ])->default(CmsPage::DRAFT)->required(),
            Select::make('template')->label(__('cms.template'))
                ->options(['standard' => __('cms.standard'), 'policy' => __('cms.policy')])->default('standard')->required(),
            TranslatableTabs::make(static fn (string $locale): array => [
                TextInput::make('title.'.$locale)->label(__('cms.title'))->required()->maxLength(160),
                Textarea::make('body.'.$locale)->label(__('cms.body'))->required()->rows(8)->maxLength(30000)
                    ->helperText(static fn (?CmsPage $record): ?string => $record?->seed_key === 'privacy' ? __('public-info.privacy.editor_help') : null)
                    ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $locale): void {
                        if ($get('status') === CmsPage::PUBLISHED && $value === __('cms.placeholder', [], $locale)) {
                            $fail(__('cms.replace_placeholder'));
                        }
                    }),
                TextInput::make('seo_title.'.$locale)->label(__('cms.seo_title'))->maxLength(160),
                Textarea::make('seo_description.'.$locale)->label(__('cms.seo_description'))->maxLength(320),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id')->columns([
            TextColumn::make('title')->label(__('cms.title'))->state(static fn (CmsPage $record): string => $record->title),
            TextColumn::make('slug')->label(__('cms.slug'))->searchable(),
            TextColumn::make('status')->label(__('cms.status'))->badge()
                ->formatStateUsing(static fn (string $state): string => __('cms.'.$state))
                ->color(static fn (string $state): string => $state === CmsPage::PUBLISHED ? 'success' : 'gray'),
        ])->recordActions(CompactActions::group([
            EditAction::make()
                ->mutateRecordDataUsing(static fn (CmsPage $record, array $data): array => [...$data, ...$record->getTranslations()])
                ->using(static fn (CmsPage $record, array $data): CmsPage => app(SaveCmsContent::class)($record, $data)),
            Action::make('preview')->label(__('cms.preview'))->icon('heroicon-o-eye')->openUrlInNewTab()
                ->url(static fn (CmsPage $record): string => URL::temporarySignedRoute('cms.preview', now()->addMinutes(15), ['page' => $record])),
        ]), CompactActions::POSITION);
    }

    public static function getPages(): array
    {
        return ['index' => ListCmsPages::route('/')];
    }
}
