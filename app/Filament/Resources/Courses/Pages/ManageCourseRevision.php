<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Revisions\Actions\DiscardCourseRevision;
use App\Domain\Catalog\Revisions\Actions\OpenCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SaveCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SubmitCourseRevision;
use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Domain\Catalog\Revisions\Queries\CourseRevisions;
use App\Domain\Catalog\Revisions\RevisionDiff;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Schemas\CourseRevisionForm;
use App\Models\Course;
use App\Models\CourseRevision;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * T-P3-07: the revision workspace of a published course. Shows the live-version notice, the open revision with its compare
 * summary and the recent history; the actions open, edit, submit and discard a revision. Same gate as editing the course.
 */
final class ManageCourseRevision extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.courses.manage-revision';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(Gate::allows('update', $this->record), 403);
    }

    public function getTitle(): string
    {
        return __('course_revisions.page');
    }

    public function getSubheading(): string
    {
        return $this->course()->title;
    }

    public function getBreadcrumbs(): array
    {
        return [CourseResource::getUrl('index') => __('courses.title'), '#' => __('course_revisions.page')];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $course = $this->course();
        $open = CourseRevisions::open($course);

        return [
            'course' => $course, 'open' => $open, 'changes' => $open === null ? [] : RevisionDiff::between($open->base, $open->payload),
            'history' => CourseRevisions::history($course), 'live' => $course->status === CourseStatus::Published,
        ];
    }

    protected function getHeaderActions(): array
    {
        $open = fn (): ?CourseRevision => CourseRevisions::open($this->course());
        $draft = fn (): ?CourseRevision => ($revision = $open()) !== null && $revision->status === CourseRevisionStatus::Draft ? $revision : null;

        return [
            Action::make('openRevision')->label(__('course_revisions.open'))->icon('heroicon-o-pencil-square')->color('primary')->requiresConfirmation()->modalDescription(__('course_revisions.open_confirm'))
                ->visible(fn (): bool => $this->course()->status === CourseStatus::Published && $open() === null)
                ->action(fn () => $this->run(fn (User $actor) => app(OpenCourseRevision::class)($actor, $this->course(), CourseRevisions::lastRejected($this->course())), 'opened')),
            Action::make('editRevision')->label(__('course_revisions.edit'))->icon('heroicon-o-pencil')->slideOver()->modalWidth('5xl')->modalHeading(__('course_revisions.edit'))
                ->visible(fn (): bool => $draft() !== null)
                ->schema(CourseRevisionForm::make())
                ->fillForm(function () use ($draft): array {
                    $revision = $draft();

                    return CourseRevisionForm::fill($revision !== null ? $revision->payload : []);
                })
                ->action(function (array $data, Action $action) use ($draft): void {
                    $revision = $draft();
                    abort_unless($revision instanceof CourseRevision, 404);
                    if (! $this->guard(fn (User $actor) => app(SaveCourseRevision::class)($actor, $revision, $data), 'saved')) {
                        $action->halt();
                    }
                }),
            Action::make('submitRevision')->label(__('course_revisions.submit'))->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()->modalDescription(__('course_revisions.submit_confirm'))
                ->visible(fn (): bool => $draft() !== null)
                ->action(fn () => $this->run(function (User $actor) use ($draft): CourseRevision {
                    $revision = $draft();
                    abort_unless($revision instanceof CourseRevision, 404);

                    return app(SubmitCourseRevision::class)($actor, $revision);
                }, 'submitted')),
            Action::make('discardRevision')->label(__('course_revisions.discard'))->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()->modalDescription(__('course_revisions.discard_confirm'))
                ->visible(fn (): bool => $open() !== null)
                ->action(fn () => $this->run(function (User $actor) use ($open): CourseRevision {
                    $revision = $open();
                    abort_unless($revision instanceof CourseRevision, 404);

                    return app(DiscardCourseRevision::class)($actor, $revision);
                }, 'discarded')),
        ];
    }

    /** @param Closure(User): mixed $call */
    private function run(Closure $call, string $done): void
    {
        $this->guard($call, $done);
    }

    /** @param Closure(User): mixed $call */
    private function guard(Closure $call, string $done): bool
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);

        try {
            $call($actor);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            return false;
        }
        Notification::make()->success()->title(__('course_revisions.done.'.$done))->send();

        return true;
    }

    private function course(): Course
    {
        /** @var Course $course */
        $course = $this->record;

        return $course->refresh();
    }
}
