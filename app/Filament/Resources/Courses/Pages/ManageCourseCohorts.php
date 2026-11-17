<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\PromoteFromWaitlist;
use App\Domain\Cohorts\Actions\RemoveFromCohort;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortFullException;
use App\Domain\Cohorts\CohortMember;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** T-P5-01: cohorts of a live or hybrid course with capacity, members and the waitlist. Seats only; course access stays with payment or manual grants. */
final class ManageCourseCohorts extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.courses.cohorts';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('live_session.manage') && Gate::forUser($user)->allows('update', $this->record), 403);
    }

    public function getTitle(): string
    {
        return __('cohorts.title');
    }

    protected function getHeaderActions(): array
    {
        return [$this->addCohortAction()];
    }

    public function addCohortAction(): Action
    {
        return Action::make('addCohort')->label(__('cohorts.add_cohort'))->icon('heroicon-o-plus')
            ->visible(fn (): bool => in_array($this->course()->format, ['live', 'hybrid'], true))
            ->schema($this->schema())
            ->action(function (array $data, Action $action): void {
                try {
                    app(ManageCohorts::class)->create($this->actor(), $this->course(), $data);
                } catch (ValidationException $exception) {
                    $this->refuse($exception);
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('cohorts.saved'))->send();
            });
    }

    public function editCohortAction(): Action
    {
        return Action::make('editCohort')->label(__('cohorts.edit'))->modalHeading(__('cohorts.edit'))
            ->schema($this->schema())
            ->fillForm(fn (array $arguments): array => $this->cohortFor($arguments['cohort'] ?? 0)->only(['name', 'capacity', 'starts_on', 'ends_on']))
            ->action(function (array $data, array $arguments, Action $action): void {
                try {
                    app(ManageCohorts::class)->update($this->actor(), $this->cohortFor($arguments['cohort'] ?? 0), $data);
                } catch (ValidationException $exception) {
                    $this->refuse($exception);
                    $action->halt();

                    return;
                }
                Notification::make()->success()->title(__('cohorts.saved'))->send();
            });
    }

    public function promote(int $cohortId): void
    {
        $cohort = $this->cohortFor($cohortId);
        try {
            $member = app(PromoteFromWaitlist::class)($this->actor(), $cohort);
            Notification::make()->success()->title(__('cohorts.promoted', ['name' => (string) $member->learner->name]))->send();
        } catch (CohortFullException) {
            Notification::make()->danger()->title(__('cohorts.full'))->send();
        } catch (ValidationException $exception) {
            $this->refuse($exception);
        }
    }

    public function remove(int $memberId): void
    {
        $member = CohortMember::query()->with('cohort')->findOrFail($memberId);
        abort_unless((int) $member->cohort->course_id === (int) $this->course()->getKey(), 404);
        app(RemoveFromCohort::class)($this->actor(), $member);
        Notification::make()->success()->title(__('cohorts.removed'))->send();
    }

    public function toggleStatus(int $cohortId): void
    {
        $cohort = $this->cohortFor($cohortId);
        app(ManageCohorts::class)->setStatus($this->actor(), $cohort, $cohort->status === Cohort::OPEN ? Cohort::CLOSED : Cohort::OPEN);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'course' => $this->course(),
            'cohorts' => Cohort::query()->where('course_id', $this->course()->getKey())->with(['instructors', 'members' => fn ($members) => $members->where('status', '!=', CohortMember::REMOVED)->with('learner')->orderBy('queued_at')->orderBy('id')])->orderBy('id')->get(),
        ];
    }

    /** @return list<mixed> */
    private function schema(): array
    {
        return [
            TextInput::make('name')->label(__('cohorts.name'))->required()->maxLength(120),
            TextInput::make('capacity')->label(__('cohorts.capacity'))->numeric()->minValue(1)->helperText(__('cohorts.unlimited')),
            DatePicker::make('starts_on')->label(__('cohorts.starts_on')),
            DatePicker::make('ends_on')->label(__('cohorts.ends_on'))->afterOrEqual('starts_on'),
        ];
    }

    private function refuse(ValidationException $exception): void
    {
        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
    }

    private function cohortFor(int|string $id): Cohort
    {
        return Cohort::query()->where('course_id', $this->course()->getKey())->findOrFail($id);
    }

    private function course(): Course
    {
        /** @var Course $course */
        $course = $this->record;

        return $course;
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
