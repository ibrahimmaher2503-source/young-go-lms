<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlogPosts;

use App\Domain\Content\BlogPostStatus;
use App\Domain\Shared\Search\ArabicNormalizer;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    public static function getModelLabel(): string
    {
        return __('blog.post');
    }

    public static function getPluralModelLabel(): string
    {
        return __('blog.posts');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-newspaper';
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

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(BlogPostStatus::cases())->mapWithKeys(static fn (BlogPostStatus $status): array => [$status->value => $status->label()])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')->label(__('blog.status'))->options(self::statusOptions())
                ->default(BlogPostStatus::Draft->value)->required()->live(),
            DateTimePicker::make('published_at')->label(__('blog.published_at'))->helperText(__('blog.published_at_help'))
                ->seconds(false)->required(static fn (Get $get): bool => $get('status') === BlogPostStatus::Scheduled->value),
            Select::make('blog_category_id')->label(__('blog.category_field'))
                ->options(static fn (): array => BlogCategory::query()->where('status', BlogCategory::ACTIVE)->orderBy('sort')->orderBy('id')->get()
                    ->mapWithKeys(static fn (BlogCategory $category): array => [$category->id => $category->name])->all())
                ->searchable(),
            Select::make('author_id')->label(__('blog.author'))->default(static fn (): int|string|null => Auth::id())
                ->options(static fn (): array => User::query()->permission('content.blog.manage')->orderBy('name')->pluck('name', 'id')->all())
                ->searchable(),
            Select::make('tags')->label(__('blog.tags_field'))->multiple()
                ->options(static fn (): array => BlogTag::query()->orderBy('id')->get()->mapWithKeys(static fn (BlogTag $tag): array => [$tag->id => $tag->name])->all())
                ->searchable(),
            FileUpload::make('featured_image')->label(__('blog.featured_image'))->disk('public')->directory('blog')
                ->visibility('public')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(4096)->preventFilePathTampering(),
            TranslatableTabs::make(static fn (string $locale): array => [
                TextInput::make('title.'.$locale)->label(__('blog.post_title'))->required()->maxLength(160),
                TextInput::make('slug_'.$locale)->label(__('blog.slug'))->required()->maxLength(120)
                    ->regex('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u')->unique(table: BlogPost::class, ignoreRecord: true),
                Textarea::make('excerpt.'.$locale)->label(__('blog.excerpt'))->maxLength(400)->rows(3),
                Textarea::make('body.'.$locale)->label(__('blog.body'))->required()->rows(14)->maxLength(60000),
                TextInput::make('featured_image_alt.'.$locale)->label(__('blog.image_alt'))->maxLength(200),
                TextInput::make('seo_title.'.$locale)->label(__('blog.seo_title'))->maxLength(160),
                Textarea::make('seo_description.'.$locale)->label(__('blog.seo_description'))->maxLength(320),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('title')->label(__('blog.post_title'))->state(static fn (BlogPost $record): string => $record->title)
                ->searchable(query: static fn (Builder $query, string $search): Builder => $query->whereLike('blog_posts.search_text', '%'.ArabicNormalizer::normalize($search).'%')),
            TextColumn::make('status')->label(__('blog.status'))->badge()
                ->state(static fn (BlogPost $record): string => $record->status->label().' · '.($record->isLive() ? __('blog.live') : __('blog.not_live')))
                ->color(static fn (BlogPost $record): string => $record->isLive() ? 'success' : 'gray'),
            TextColumn::make('published_at')->label(__('blog.published_at'))->dateTime()->sortable(),
            TextColumn::make('category')->label(__('blog.category_field'))->state(static fn (BlogPost $record): string => $record->category->name ?? '—'),
            TextColumn::make('author')->label(__('blog.author'))->state(static fn (BlogPost $record): string => $record->author->name ?? '—'),
        ])->filters([
            SelectFilter::make('status')->label(__('blog.status'))->options(self::statusOptions()),
        ])->recordActions([
            EditAction::make()
                ->mutateRecordDataUsing(static fn (BlogPost $record, array $data): array => [...$data, ...$record->getTranslations(), 'tags' => $record->tags()->pluck('tags.id')->all()])
                ->using(static fn (ListBlogPosts $livewire, BlogPost $record, array $data): BlogPost => $livewire->savePost($data, $record)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBlogPosts::route('/')];
    }
}
