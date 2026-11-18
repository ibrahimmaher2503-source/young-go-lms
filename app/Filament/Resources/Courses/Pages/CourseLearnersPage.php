<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Progress\Queries\LearnerCourseDetail;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** T-P4-10: the learners of one course with their progress, for the people who run it. */
final class CourseLearnersPage extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.courses.learners';

    /** @var array<int, array{quizzes?: list<string>, assignments?: list<string>}>|null */
    private ?array $assessmentStates = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizedCourse();
    }

    public function getTitle(): string
    {
        return __('courses.learners_title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => LearnerCourseDetail::courseLearners($this->authorizedCourse()))
            ->defaultSort('users.name')
            ->paginated([15])
            ->emptyStateHeading(__('courses.learners_empty'))
            ->columns([
                TextColumn::make('name')->label(__('courses.learner')),
                TextColumn::make('access_status')->label(__('courses.access'))->badge()
                    ->formatStateUsing(fn (string $state): string => __('courses.access_'.$state)),
                TextColumn::make('enrolled_at')->label(__('courses.enrolled_at'))
                    ->formatStateUsing(fn (string $state): string => Carbon::parse($state)->isoFormat('LL')),
                TextColumn::make('percent')->label(__('courses.progress'))->default(0)->suffix('%'),
                TextColumn::make('last_activity_at')->label(__('courses.last_activity'))
                    ->formatStateUsing(fn (string $state): string => Carbon::parse($state)->isoFormat('LLL'))->placeholder('—'),
                TextColumn::make('completed_items')->label(__('courses.completed_items'))->default(0),
                TextColumn::make('quizzes')->label(__('courses.quizzes'))
                    ->state(fn (User $record): array => $this->assessmentStates($record, 'quizzes'))->listWithLineBreaks()->placeholder('—'),
                TextColumn::make('assignments')->label(__('courses.assignments'))
                    ->state(fn (User $record): array => $this->assessmentStates($record, 'assignments'))->listWithLineBreaks()->placeholder('—'),
            ])
            ->recordActions([
                Action::make('details')->label(__('courses.details'))->modalHeading(__('courses.details'))
                    ->modalContent(fn (User $record): View => view('filament.courses.learner-detail', [
                        'detail' => LearnerCourseDetail::for($record, $this->authorizedCourse()),
                    ]))
                    ->modalSubmitAction(false),
            ]);
    }

    private function authorizedCourse(): Course
    {
        $user = Auth::user();
        $course = $this->getRecord();
        abort_unless($course instanceof Course && $user instanceof User && $user->can('learner.progress.view'), 403);
        Gate::forUser($user)->authorize('view', $course);

        return $course;
    }

    /** @param 'quizzes'|'assignments' $type
     * @return list<string>
     */
    private function assessmentStates(User $learner, string $type): array
    {
        $this->assessmentStates ??= LearnerCourseDetail::assessmentStates($this->authorizedCourse(), $this->getTableRecords()->pluck('id')->all());

        return $this->assessmentStates[$learner->id][$type] ?? [];
    }
}
