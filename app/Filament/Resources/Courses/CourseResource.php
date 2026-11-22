<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses;

use App\Domain\Catalog\Actions\ApproveCourse;
use App\Domain\Catalog\Actions\ArchiveCourse;
use App\Domain\Catalog\Actions\DeleteCourse;
use App\Domain\Catalog\Actions\DuplicateCourse;
use App\Domain\Catalog\Actions\FeatureCourse;
use App\Domain\Catalog\Actions\RejectCourse;
use App\Domain\Catalog\Actions\ReviewCourse;
use App\Domain\Catalog\CourseDescription;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Queries\CourseDirectory;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Entitlements\CourseLearners;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use App\Domain\Shared\Money\PriceFormatter;
use App\Domain\Shared\Search\ArabicNormalizer;
use App\Filament\Resources\Courses\Pages\CourseLearnersPage;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\Pages\ManageCourseCohorts;
use App\Filament\Resources\Courses\Pages\ManageCourseCompletion;
use App\Filament\Resources\Courses\Pages\ManageCourseRevision;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Filament\Resources\Courses\Pages\ManageQuiz;
use App\Filament\Resources\Courses\Pages\ReviewCurriculumDocument;
use App\Filament\Resources\Courses\Pages\ViewCourse;
use App\Filament\Shared\Actions\ApproveRejectAction;
use App\Filament\Shared\Tables\CompactActions;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
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
        $market = Market::Egypt;
        $canViewLearners = Auth::user()?->can('learner.progress.view') ?? false;
        // T-P3-03: review queue buttons. RecordReviewDecision rechecks the `publish` grant, runs the mutation and audits it.
        [$approve, $reject] = ApproveRejectAction::make(
            ReviewCourse::ABILITY,
            ReviewCourse::EVENT_PREFIX,
            static function (Model $record, User $actor, ?string $note): void {
                app(ApproveCourse::class)(self::course($record), $actor, $note);
            },
            static function (Model $record, User $actor, ?string $reason): void {
                app(RejectCourse::class)(self::course($record), $actor, $reason);
            },
        );
        $pendingOnly = static fn (Course $record): bool => $record->status === CourseStatus::PendingReview;

        return $table->extraAttributes(['class' => 'yg-compact-table'], merge: true)->defaultSort('id', 'desc')->modifyQueryUsing(static function (Builder $query) use ($market, $canViewLearners): Builder {
            return $query->addSelect([
                'sections_count' => CourseSection::query()->selectRaw('count(*)')->whereColumn('course_id', 'courses.id')->whereNull('archived_at'),
                'lessons_count' => CurriculumItem::query()->selectRaw('count(*)')->whereColumn('course_id', 'courses.id')
                    ->where('itemable_type', 'lesson')
                    ->whereIn('itemable_id', Lesson::query()->select('id')->whereNull('archived_at'))
                    ->whereIn('section_id', CourseSection::query()->select('id')->whereNull('archived_at')),
                'market_price' => CoursePrice::query()->select('amount_minor')->whereColumn('course_id', 'courses.id')
                    ->where('market', $market->value)->where('currency', $market->currency())
                    ->where('amount_minor', '>', 0)->whereIn('courses.access_mode', Course::INDIVIDUAL_ACCESS_MODES)->limit(1),
            ])->when($canViewLearners, static fn (Builder $directory): Builder => $directory->addSelect(['enrollments_count' => CourseLearners::countForDirectory()]));
        })->columns([
            TextColumn::make('title')->label(__('courses.name'))->state(static fn (Course $record): string => $record->title)
                ->searchable(query: static fn (Builder $query, string $search): Builder => $query->whereLike(
                    'search_text',
                    '%'.ArabicNormalizer::normalize($search).'%',
                ))
                ->sortable(query: static function (Builder $query, string $direction): Builder {
                    $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
                    $dir = $direction === 'desc' ? 'desc' : 'asc';

                    return $query->orderBy('courses.title->'.$locale, $dir);
                }),
            TextColumn::make('category.name')->label(__('courses.category'))
                ->state(static fn (Course $record): string => $record->category->name ?? __('courses.unassigned'))
                ->sortable(query: static function (Builder $query, string $direction): Builder {
                    $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
                    $dir = $direction === 'desc' ? 'desc' : 'asc';

                    return $query->orderBy(
                        Category::query()->select('name->'.$locale)->whereColumn('categories.id', 'courses.category_id')->limit(1),
                        $dir,
                    );
                }),
            TextColumn::make('instructors.name')->label(__('courses.instructors'))
                ->state(static fn (Course $record): string => $record->instructors->pluck('name')->implode(', ') ?: __('courses.unassigned'))
                ->sortable(query: static function (Builder $query, string $direction): Builder {
                    $dir = $direction === 'desc' ? 'desc' : 'asc';

                    return $query->orderBy(
                        User::query()
                            ->select('users.name')
                            ->join('course_instructors', 'course_instructors.instructor_id', '=', 'users.id')
                            ->whereColumn('course_instructors.course_id', 'courses.id')
                            ->orderBy('users.name')
                            ->limit(1),
                        $dir,
                    );
                }),
            TextColumn::make('sections_count')->visibleFrom('md')->label(__('courses.sections'))->sortable(),
            TextColumn::make('lessons_count')->label(__('courses.lessons'))->sortable(),
            TextColumn::make('enrollments_count')->visibleFrom('md')->label(__('courses.enrollments'))->visible($canViewLearners)->sortable(),
            TextColumn::make('market_price')->label(__('courses.price'))->sortable()
                ->placeholder(static fn (Course $record): string => match ($record->access_mode) {
                    Course::ACCESS_FREE => __('courses.free'),
                    Course::ACCESS_SUBSCRIPTION => __('catalog.included_in_subscription'),
                    default => __('courses.unconfigured'),
                })
                ->formatStateUsing(static fn ($state): string => app(PriceFormatter::class)->format(Money::ofMinor((int) $state, $market->currency()), $market, app()->getLocale())),
            StatusBadgeColumn::make('status', ['published' => 'success', 'upcoming' => 'info', 'suspended' => 'warning', 'pending_review' => 'warning'])
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
                Course::ACCESS_SUBSCRIPTION => __('courses.access_subscription'), Course::ACCESS_BOTH => __('courses.access_both'),
            ]),
            SelectFilter::make('status')->label(__('courses.status'))->options(array_combine(
                array_column(CourseStatus::cases(), 'value'),
                array_map(static fn (CourseStatus $status): string => __('courses.'.$status->value), CourseStatus::cases()),
            )),
        ])->recordActions(CompactActions::group([
            ViewAction::make(),
            EditAction::make(),
            $approve->visible($pendingOnly),
            $reject->visible($pendingOnly),
            Action::make('curriculum')->label(__('curriculum.curriculum'))->icon('heroicon-o-queue-list')
                ->url(static fn (Course $record): string => self::getUrl('curriculum', ['record' => $record]))
                ->visible(static fn (Course $record): bool => Gate::allows('update', $record)),
            Action::make('revision')->label(__('course_revisions.page'))->icon('heroicon-o-document-duplicate')
                ->url(static fn (Course $record): string => self::getUrl('revision', ['record' => $record]))
                ->visible(static fn (Course $record): bool => $record->status === CourseStatus::Published && Gate::allows('update', $record)),
            Action::make('cohorts')->label(__('cohorts.title'))->icon('heroicon-o-user-group')
                ->url(static fn (Course $record): string => self::getUrl('cohorts', ['record' => $record]))
                ->visible(static fn (Course $record): bool => in_array($record->format, ['live', 'hybrid'], true) && Auth::user()?->can('live_session.manage') === true && Gate::allows('update', $record)),
            Action::make('completion')->label(__('completion.title'))->icon('heroicon-o-check-badge')
                ->url(static fn (Course $record): string => self::getUrl('completion', ['record' => $record]))
                ->visible(static fn (Course $record): bool => Gate::allows('update', $record)),
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
        ]), CompactActions::POSITION);
    }

    private static function course(Model $record): Course
    {
        abort_unless($record instanceof Course, 404);

        return $record;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['lg' => 3])->components([
            Section::make(__('courses.overview'))->icon('heroicon-o-document-text')
                ->columnSpan(['lg' => 2])->extraAttributes(['data-course-overview' => ''])
                ->schema([
                    ImageEntry::make('cover')->hiddenLabel()
                        ->state(static fn (Course $record): ?string => ($url = $record->coverUrl()) ? asset($url) : null)
                        ->hidden(static fn (Course $record): bool => $record->coverUrl() === null)
                        ->width('100%')->height('auto')
                        ->extraImgAttributes(['class' => 'aspect-video rounded-lg object-cover']),
                    TextEntry::make('description')->hiddenLabel()->prose()->size('md')
                        ->state(static fn (Course $record): ?string => filled($record->description) ? $record->description : $record->short_description)
                        ->placeholder(__('courses.description_empty'))
                        ->formatStateUsing(static fn (string $state): HtmlString => new HtmlString(CourseDescription::clean($state))),
                ]),
            Section::make(__('courses.details'))->icon('heroicon-o-information-circle')
                ->extraAttributes(['data-course-facts' => ''])->columns(['default' => 2])
                ->schema([
                    TextEntry::make('status')->label(__('courses.status'))->badge()
                        ->color(static fn (CourseStatus $state): string => match ($state) {
                            CourseStatus::Published => 'success',
                            CourseStatus::Upcoming => 'info',
                            CourseStatus::PendingReview, CourseStatus::Suspended => 'warning',
                            default => 'gray',
                        })
                        ->formatStateUsing(static fn (CourseStatus $state): string => __('courses.'.$state->value)),
                    TextEntry::make('format')->label(__('courses.format'))->formatStateUsing(static fn (string $state): string => __('courses.'.$state)),
                    TextEntry::make('category.name')->label(__('courses.category'))->placeholder(__('courses.unassigned')),
                    TextEntry::make('owner_type')->label(__('courses.ownership'))->formatStateUsing(static fn (string $state): string => __('courses.'.$state)),
                    TextEntry::make('instructors.name')->label(__('courses.instructors'))->columnSpanFull()
                        ->listWithLineBreaks()->placeholder(__('courses.unassigned')),
                ]),
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
            'revision' => ManageCourseRevision::route('/{record}/revision'),
            'cohorts' => ManageCourseCohorts::route('/{record}/cohorts'),
            'learners' => CourseLearnersPage::route('/{record}/learners'),
            'completion' => ManageCourseCompletion::route('/{record}/completion'),
            'quiz' => ManageQuiz::route('/{record}/quizzes/{quiz}'),
            'document' => ReviewCurriculumDocument::route('/{record}/curriculum/documents/{document}'),
        ];
    }
}
