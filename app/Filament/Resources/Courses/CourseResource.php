<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses;

use App\Domain\Catalog\Actions\ArchiveCourse;
use App\Domain\Catalog\Actions\DeleteCourse;
use App\Domain\Catalog\Actions\DuplicateCourse;
use App\Domain\Catalog\Actions\FeatureCourse;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Queries\CourseDirectory;
use App\Domain\Shared\Search\ArabicNormalizer;
use App\Filament\Resources\Courses\Pages\CourseLearnersPage;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\Pages\ManageCourseCohorts;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Filament\Resources\Courses\Pages\ReviewCurriculumDocument;
use App\Filament\Resources\Courses\Pages\ViewCourse;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** @extends FilamentResource<Course> */
final class CourseResource extends FilamentResource
{
    protected static ?string $model = Course::class;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('courses.course');
    }

    public static function getPluralModelLabel(): string
    {
        return __('courses.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-academic-cap';
    }

    /** @return Builder<Course> */
    public static function getEloquentQuery(): Builder
    {
        return CourseDirectory::for(Auth::user())->with(['category', 'instructors']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('title')->label(__('courses.name'))->state(static fn (Course $record): string => $record->title)
                ->searchable(query: static fn (Builder $query, string $search): Builder => $query->whereLike(
                    'search_text',
                    '%'.ArabicNormalizer::normalize($search).'%',
                )),
            TextColumn::make('category.name')->label(__('courses.category'))
                ->state(static fn (Course $record): string => $record->category->name ?? __('courses.unassigned')),
            TextColumn::make('instructors.name')->label(__('courses.instructors'))
                ->state(static fn (Course $record): string => $record->instructors->pluck('name')->implode(', ') ?: __('courses.unassigned')),
            TextColumn::make('access_mode')->label(__('courses.access'))->state(
                static fn (Course $record): string => __('courses.'.($record->access_mode ?? 'unconfigured')),
            )->sortable(),
            StatusBadgeColumn::make('status', ['published' => 'success', 'upcoming' => 'info', 'suspended' => 'warning'])
                ->label(__('courses.status'))->formatStateUsing(static fn (CourseStatus $state): string => __('courses.'.$state->value))->sortable(),
        ])->filters([
            SelectFilter::make('category_id')->label(__('courses.category'))
                ->options(static fn (): array => Category::query()->whereIn('id', CourseDirectory::for(Auth::user())->select('category_id'))
                    ->orderBy('sort')->get()->mapWithKeys(static fn (Category $category): array => [$category->id => $category->name])->all()),
            SelectFilter::make('instructor')->label(__('courses.instructors'))
                ->options(static fn (): array => User::query()->whereIn('id', CourseDirectory::for(Auth::user())
                    ->join('course_instructors as directory_assignments', 'directory_assignments.course_id', '=', 'courses.id')
                    ->select('directory_assignments.instructor_id'))->orderBy('name')->pluck('name', 'id')->all())
                ->query(static function (Builder $query, array $data): Builder {
                    $id = $data['value'] ?? null;

                    return $id ? $query->whereHas('instructors', static fn (Builder $users): Builder => $users->whereKey($id)) : $query;
                }),
            SelectFilter::make('access_mode')->label(__('courses.access'))->options([
                Course::ACCESS_PAID => __('courses.paid'), Course::ACCESS_FREE => __('courses.free'),
            ]),
            SelectFilter::make('status')->label(__('courses.status'))->options(array_combine(
                array_column(CourseStatus::cases(), 'value'),
                array_map(static fn (CourseStatus $status): string => __('courses.'.$status->value), CourseStatus::cases()),
            )),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make(),
            Action::make('curriculum')->label(__('curriculum.curriculum'))->icon('heroicon-o-queue-list')
                ->url(static fn (Course $record): string => self::getUrl('curriculum', ['record' => $record]))
                ->visible(static fn (Course $record): bool => Gate::allows('update', $record)),
            Action::make('cohorts')->label(__('cohorts.title'))->icon('heroicon-o-user-group')
                ->url(static fn (Course $record): string => self::getUrl('cohorts', ['record' => $record]))
                ->visible(static fn (Course $record): bool => in_array($record->format, ['live', 'hybrid'], true) && Auth::user()?->can('live_session.manage') === true && Gate::allows('update', $record)),
            Action::make('learners')->label(__('courses.learners_title'))->icon('heroicon-o-users')
                ->url(static fn (Course $record): string => self::getUrl('learners', ['record' => $record]))
                ->visible(static fn (Course $record): bool => Auth::user()?->can('learner.progress.view') === true && Gate::allows('view', $record)),
            Action::make('duplicate')->label(__('courses.duplicate'))->icon('heroicon-o-document-duplicate')->requiresConfirmation()
                ->visible(static fn (Course $record): bool => Gate::allows('duplicate', $record))
                ->action(static function (Course $record): void {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 401);
                    $copy = app(DuplicateCourse::class)($actor, $record);
                    Notification::make()->success()->title(__('courses.duplicated', ['title' => $copy->title]))->send();
                }),
            Action::make('archive')->label(__('courses.archive'))->icon('heroicon-o-archive-box')->color('gray')->requiresConfirmation()
                ->visible(static fn (Course $record): bool => Gate::allows('archive', $record) && $record->status !== CourseStatus::Archived)
                ->action(static function (Course $record): void {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 401);
                    app(ArchiveCourse::class)($actor, $record);
                    Notification::make()->success()->title(__('courses.archived'))->send();
                }),
            Action::make('delete')->label(__('courses.delete'))->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()->modalDescription(__('courses.delete_confirm'))
                ->visible(static fn (Course $record): bool => Gate::allows('delete', $record))
                ->action(static function (Course $record): void {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 401);
                    try {
                        app(DeleteCourse::class)($actor, $record);
                        Notification::make()->success()->title(__('courses.deleted'))->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
                    }
                }),
            Action::make('feature')->label(static fn (Course $record): string => __('courses.'.($record->featured ? 'unfeature' : 'feature')))
                ->icon('heroicon-o-star')->visible(static fn (Course $record): bool => Gate::allows('feature', $record))
                ->action(static fn (Course $record) => app(FeatureCourse::class)($record, ! $record->featured)),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label(__('courses.name'))->state(static fn (Course $record): string => $record->title),
            TextEntry::make('status')->label(__('courses.status'))->formatStateUsing(static fn (CourseStatus $state): string => __('courses.'.$state->value)),
            TextEntry::make('owner_type')->label(__('courses.ownership'))->formatStateUsing(static fn (string $state): string => __('courses.'.$state)),
            TextEntry::make('format')->label(__('courses.format'))->formatStateUsing(static fn (string $state): string => __('courses.'.$state)),
            TextEntry::make('category.name')->label(__('courses.category'))
                ->state(static fn (Course $record): string => $record->category->name ?? __('courses.unassigned')),
            TextEntry::make('description')->label(__('courses.description'))->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'view' => ViewCourse::route('/{record}'),
            'edit' => EditCourse::route('/{record}/edit'),
            'curriculum' => ManageCurriculum::route('/{record}/curriculum'),
            'cohorts' => ManageCourseCohorts::route('/{record}/cohorts'),
            'learners' => CourseLearnersPage::route('/{record}/learners'),
            'document' => ReviewCurriculumDocument::route('/{record}/curriculum/documents/{document}'),
        ];
    }
}
