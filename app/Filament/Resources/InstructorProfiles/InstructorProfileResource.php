<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstructorProfiles;

use App\Domain\Instructors\Queries\PublicInstructors;
use App\Filament\Resources\InstructorProfiles\Pages\ListInstructorProfiles;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Models\Category;
use App\Models\InstructorProfile;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Admin editing and approval of the public instructor profile (T-P2-09). Gate: instructor.manage. */
final class InstructorProfileResource extends Resource
{
    protected static ?string $model = InstructorProfile::class;

    public static function getModelLabel(): string
    {
        return __('instructors.profile');
    }

    public static function getPluralModelLabel(): string
    {
        return __('instructors.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-identification';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('instructor.manage');
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
            Select::make('user_id')->label(__('instructors.user'))->required()->searchable()
                ->disabled(static fn (?InstructorProfile $record): bool => $record !== null)
                ->dehydrated(static fn (?InstructorProfile $record): bool => $record === null)
                ->options(static fn (): array => User::query()->role(PublicInstructors::ROLES)->orderBy('name')->pluck('name', 'id')->all())
                ->unique(table: InstructorProfile::class, ignoreRecord: true),
            Select::make('status')->label(__('instructors.status'))->helperText(__('instructors.approved_help'))->required()
                ->default(InstructorProfile::DRAFT)->options([
                    InstructorProfile::DRAFT => __('instructors.draft'),
                    InstructorProfile::APPROVED => __('instructors.approved'),
                    InstructorProfile::HIDDEN => __('instructors.hidden'),
                ]),
            Select::make('disciplines')->label(__('instructors.disciplines_field'))->multiple()->searchable()
                ->options(static fn (): array => Category::query()->where('status', 'active')->orderBy('sort')->orderBy('id')->get()
                    ->mapWithKeys(static fn (Category $category): array => [$category->id => $category->name])->all()),
            FileUpload::make('avatar')->label(__('instructors.avatar'))->disk('public')->directory('instructors')
                ->visibility('public')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(2048)->preventFilePathTampering(),
            TranslatableTabs::make(static fn (string $locale): array => [
                TextInput::make('display_name.'.$locale)->label(__('instructors.display_name'))->helperText(__('instructors.display_name_help'))->maxLength(160),
                TextInput::make('headline.'.$locale)->label(__('instructors.headline'))->maxLength(200),
                Textarea::make('bio.'.$locale)->label(__('instructors.bio'))->rows(8)->maxLength(5000),
                TextInput::make('slug_'.$locale)->label(__('instructors.slug'))->required()->maxLength(120)
                    ->regex('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u')->unique(table: InstructorProfile::class, ignoreRecord: true),
                TextInput::make('seo_title.'.$locale)->label(__('instructors.seo_title'))->maxLength(160),
                Textarea::make('seo_description.'.$locale)->label(__('instructors.seo_description'))->maxLength(320),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('name')->label(__('instructors.display_name'))->state(static fn (InstructorProfile $record): string => $record->publicName()),
            TextColumn::make('status')->label(__('instructors.status'))->badge()
                ->formatStateUsing(static fn (string $state): string => __('instructors.'.$state))
                ->color(static fn (string $state): string => $state === InstructorProfile::APPROVED ? 'success' : 'gray'),
            TextColumn::make('approved_at')->label(__('instructors.approved'))->dateTime(),
        ])->recordActions([
            EditAction::make()
                ->mutateRecordDataUsing(static fn (InstructorProfile $record, array $data): array => [
                    ...$data,
                    ...$record->getTranslations(),
                    'disciplines' => $record->disciplines()->pluck('categories.id')->all(),
                ])
                ->using(static fn (ListInstructorProfiles $livewire, InstructorProfile $record, array $data): InstructorProfile => $livewire->saveProfile($data, $record)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInstructorProfiles::route('/')];
    }
}
