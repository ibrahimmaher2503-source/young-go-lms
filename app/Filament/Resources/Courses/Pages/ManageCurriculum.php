<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\MoveCurriculumItem;
use App\Domain\Curriculum\Actions\ReorderCurriculum;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Course curriculum manager: sections and lessons, drag and keyboard ordering, bulk publish, archive, student preview. */
final class ManageCurriculum extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.courses.manage-curriculum';

    /** @var list<int> */
    public array $selected = [];

    public bool $previewing = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(Gate::allows('update', $this->record), 403);
    }

    public function getTitle(): string
    {
        return __('curriculum.page');
    }

    public function getSubheading(): string
    {
        return $this->course()->title;
    }

    public function getBreadcrumbs(): array
    {
        return [CourseResource::getUrl('index') => __('courses.title'), '#' => __('curriculum.curriculum')];
    }

    public function course(): Course
    {
        /** @var Course $course */
        $course = $this->record;

        return $course;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'sections' => CurriculumOutline::forManager($this->course()),
            'archived' => CurriculumOutline::archivedItems($this->course()),
            'student' => $this->previewing ? CurriculumOutline::forStudents($this->course()) : collect(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addLessonAction(),
            $this->addSectionAction(),
            Action::make('studentPreview')->label(__('curriculum.student_preview'))->icon('heroicon-o-eye')->color('gray')
                ->action(function (): void {
                    $this->previewing = ! $this->previewing;
                }),
        ];
    }

    public function addSectionAction(): Action
    {
        return Action::make('addSection')->label(__('curriculum.add_section'))->icon('heroicon-o-rectangle-stack')->color('gray')
            ->schema([
                TextInput::make('title.ar')->label(__('curriculum.section_title').' (AR)')->maxLength(150)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                TextInput::make('title.en')->label(__('curriculum.section_title').' (EN)')->maxLength(150)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            ])
            ->action(function (array $data): void {
                app(ManageSections::class)->add($this->actor(), $this->course(), $data);
                Notification::make()->title(__('curriculum.saved'))->success()->send();
            });
    }

    public function addLessonAction(): Action
    {
        return Action::make('addLesson')->label(__('curriculum.add_lesson'))->icon('heroicon-o-plus')
            ->schema($this->lessonSchema(withSection: true))
            ->action(function (array $data): void {
                $section = CourseSection::query()->where('course_id', $this->course()->getKey())->whereNull('archived_at')->findOrFail($data['section_id']);
                app(ManageLessons::class)->add($this->actor(), $section, $this->lessonData($data));
                Notification::make()->title(__('curriculum.saved'))->success()->send();
            });
    }

    public function editLessonAction(): Action
    {
        return Action::make('editLesson')->label(__('curriculum.edit'))->modalHeading(__('curriculum.lesson_settings'))
            ->schema($this->lessonSchema(withSection: false))
            ->fillForm(function (array $arguments): array {
                $lesson = $this->lessonFor($arguments['lesson'] ?? 0);

                return [
                    'title' => $lesson->getTranslations('title'), 'type' => $lesson->type, 'is_preview' => $lesson->is_preview,
                    'duration_minutes' => $lesson->duration_seconds === null ? null : (int) round($lesson->duration_seconds / 60),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                app(ManageLessons::class)->update($this->actor(), $this->lessonFor($arguments['lesson'] ?? 0), $this->lessonData($data));
                Notification::make()->title(__('curriculum.saved'))->success()->send();
            });
    }

    public function archiveLessonAction(): Action
    {
        return Action::make('archiveLesson')->label(__('curriculum.archive'))->color('danger')->requiresConfirmation()
            ->action(function (array $arguments): void {
                app(ManageLessons::class)->archive($this->actor(), $this->lessonFor($arguments['lesson'] ?? 0));
                $this->selected = [];
            });
    }

    public function archiveSectionAction(): Action
    {
        return Action::make('archiveSection')->label(__('curriculum.archive_section'))->color('danger')->requiresConfirmation()
            ->action(function (array $arguments): void {
                app(ManageSections::class)->archive($this->actor(), $this->sectionFor($arguments['section'] ?? 0));
                $this->selected = [];
            });
    }

    /** @param list<array{section_id: int|string, items: list<int|string>}> $layout */
    public function reorder(array $layout): void
    {
        app(ReorderCurriculum::class)($this->actor(), $this->course(), $layout);
    }

    public function moveItem(int $item, string $direction): void
    {
        app(MoveCurriculumItem::class)($this->actor(), $this->itemFor($item), $direction);
    }

    public function moveSection(int $section, string $direction): void
    {
        $layout = [];
        foreach (CurriculumOutline::forManager($this->course()) as $row) {
            $layout[] = ['section_id' => (int) $row->getKey(), 'items' => $row->items->pluck('id')->map(static fn ($id): int => (int) $id)->all()];
        }
        $index = array_search($section, array_column($layout, 'section_id'), true);
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index !== false && isset($layout[$target])) {
            [$layout[$index], $layout[$target]] = [$layout[$target], $layout[$index]];
            $this->reorder($layout);
        }
    }

    public function bulkStatus(string $status): void
    {
        $changed = app(SetCurriculumStatus::class)($this->actor(), $this->course(), $this->selected, $status);
        Notification::make()->title(__($status === 'published' ? 'curriculum.published_done' : 'curriculum.unpublished_done', ['count' => $changed]))->success()->send();
        $this->selected = [];
    }

    public function setSectionPublished(int $section, bool $published): void
    {
        app(ManageSections::class)->setPublished($this->actor(), $this->sectionFor($section), $published);
    }

    public function setItemStatus(int $item, string $status): void
    {
        app(SetCurriculumStatus::class)($this->actor(), $this->course(), [$item], $status);
    }

    /** @return list<mixed> */
    private function lessonSchema(bool $withSection): array
    {
        return array_values(array_filter([
            $withSection ? Select::make('section_id')->label(__('curriculum.section'))->required()
                ->options(fn (): array => CourseSection::query()->where('course_id', $this->course()->getKey())->whereNull('archived_at')->orderBy('sort')->get()
                    ->mapWithKeys(static fn (CourseSection $section): array => [$section->getKey() => $section->title])->all()) : null,
            TextInput::make('title.ar')->label(__('curriculum.lesson_title').' (AR)')->maxLength(150)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            TextInput::make('title.en')->label(__('curriculum.lesson_title').' (EN)')->maxLength(150)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            Select::make('type')->label(__('curriculum.lesson_type'))->default('video')
                ->options(array_combine(Lesson::TYPES, array_map(static fn (string $type): string => __("curriculum.types.{$type}"), Lesson::TYPES))),
            TextInput::make('duration_minutes')->label(__('curriculum.duration_minutes'))->numeric()->minValue(0)->maxValue(1440),
            Toggle::make('is_preview')->label(__('curriculum.is_preview')),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function lessonData(array $data): array
    {
        $minutes = $data['duration_minutes'] ?? null;

        return [
            'title' => $data['title'] ?? [], 'type' => $data['type'] ?? 'video', 'is_preview' => (bool) ($data['is_preview'] ?? false),
            'duration_seconds' => $minutes === null || $minutes === '' ? null : (int) round(((float) $minutes) * 60),
        ];
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function sectionFor(int|string $id): CourseSection
    {
        return CourseSection::query()->where('course_id', $this->course()->getKey())->findOrFail($id);
    }

    private function itemFor(int|string $id): CurriculumItem
    {
        return CurriculumItem::query()->where('course_id', $this->course()->getKey())->with('course')->findOrFail($id);
    }

    private function lessonFor(int|string $id): Lesson
    {
        $item = CurriculumItem::query()->where('course_id', $this->course()->getKey())->where('itemable_type', 'lesson')->where('itemable_id', $id)->firstOrFail();
        $lesson = $item->itemable;
        abort_unless($lesson instanceof Lesson, 404);

        return $lesson;
    }
}
