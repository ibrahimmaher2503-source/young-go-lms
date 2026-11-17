<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Entitlements\CourseLearners;
use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\Queries\LearnerCourseDetail;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\User;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\WithPagination;

/** T-P4-10: the learners of one course with their progress, for the people who run it (read only). */
final class CourseLearnersPage extends Page
{
    use InteractsWithRecord;
    use WithPagination;

    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.courses.learners';

    public ?int $openLearner = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('learner.progress.view') && Gate::forUser($user)->allows('view', $this->record), 403);
    }

    public function getTitle(): string
    {
        return __('courses.learners_title');
    }

    public function toggle(int $learnerId): void
    {
        $this->openLearner = $this->openLearner === $learnerId ? null : $learnerId;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var Course $course */
        $course = $this->record;
        /** @var LengthAwarePaginator<int, User> $learners */
        $learners = CourseLearners::query($course)->orderBy('users.name')->paginate(15);
        $percent = CourseProgress::query()->where('course_id', $course->getKey())->whereIn('learner_id', $learners->pluck('id'))->pluck('percent', 'learner_id');
        $open = $this->openLearner !== null ? $learners->firstWhere('id', $this->openLearner) : null;

        return [
            'course' => $course,
            'learners' => $learners,
            'percent' => $percent,
            'detail' => $open instanceof User ? LearnerCourseDetail::for($open, $course) : [],
        ];
    }
}
