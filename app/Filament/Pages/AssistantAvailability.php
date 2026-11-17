<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\AI\Admin\Actions\SetCourseAssistant;
use App\Domain\AI\Admin\Actions\SetLessonAssistant;
use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\AI\Queries\CourseAssistantOverview;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * T-P7-06, AI spec 14: per-course and per-lesson assistant switches, each lesson with its PDF readiness. Admins see every course,
 * an instructor only the courses they manage. The global switch lives on the controls page (super admin).
 */
final class AssistantAvailability extends Page
{
    protected static ?string $slug = 'assistant-availability';

    protected string $view = 'filament.ai.availability';

    public ?int $courseId = null;

    public static function getNavigationLabel(): string
    {
        return __('ai_admin.availability.nav');
    }

    public static function getNavigationGroup(): string
    {
        return __('ai_admin.group');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && AssistantAdminAccess::canOpenCoursePages($user);
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('ai_admin.availability.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function toggleCourse(): void
    {
        $course = $this->course();
        if ($course === null) {
            return;
        }

        $this->attempt(fn (User $user) => app(SetCourseAssistant::class)($user, $course, ! $course->ai_enabled));
    }

    public function toggleLesson(int $itemId): void
    {
        $course = $this->course();
        $item = $course === null ? null : CurriculumItem::query()->where('course_id', $course->getKey())->where('itemable_type', 'lesson')->find($itemId);
        if ($item === null) {
            return;
        }

        $this->attempt(fn (User $user) => app(SetLessonAssistant::class)($user, $item, ! $item->ai_enabled));
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $overview = app(CourseAssistantOverview::class);
        $courses = $overview->courses($this->actor());
        $selected = $courses->firstWhere('id', $this->courseId);

        return [
            'courses' => $courses->mapWithKeys(static fn (Course $course): array => [(int) $course->getKey() => (string) $course->title])->all(),
            'course' => $selected,
            'lessons' => $selected === null ? [] : $overview->lessons($selected),
        ];
    }

    private function course(): ?Course
    {
        // The id comes from the browser: only a course of the permitted list resolves.
        return app(CourseAssistantOverview::class)->courses($this->actor())->firstWhere('id', $this->courseId);
    }

    /** @param  callable(User): mixed  $run */
    private function attempt(callable $run): void
    {
        try {
            $run($this->actor());
        } catch (AuthorizationException) {
            abort(403);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            return;
        }

        Notification::make()->success()->title(__('ai_admin.availability.saved'))->send();
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
