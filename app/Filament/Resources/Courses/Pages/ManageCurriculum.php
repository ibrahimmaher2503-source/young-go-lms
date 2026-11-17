<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\MoveCurriculumItem;
use App\Domain\Curriculum\Actions\ReorderCurriculum;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Actions\UploadLessonMedia;
use App\Domain\Media\MediaProviders;
use App\Domain\Media\Providers\PrivateFileProvider;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\File;

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
            ->action(function (array $data, Action $action): void {
                $section = CourseSection::query()->where('course_id', $this->course()->getKey())->whereNull('archived_at')->findOrFail($data['section_id']);

                // The lesson and its media are saved together: a refused file leaves no half-created lesson.
                if (! $this->attempt($action, function () use ($section, $data): void {
                    DB::transaction(function () use ($section, $data): void {
                        $item = app(ManageLessons::class)->add($this->actor(), $section, $this->lessonData($data));
                        $lesson = $item->itemable;
                        if ($lesson instanceof Lesson) {
                            $this->saveMedia($lesson, $data);
                        }
                    });
                })) {
                    return;
                }
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
                    'title' => $lesson->getTranslations('title'), 'description' => $lesson->getTranslations('description'), 'type' => $lesson->type, 'is_preview' => $lesson->is_preview,
                    'duration_minutes' => $lesson->duration_seconds === null ? null : (int) round($lesson->duration_seconds / 60),
                ];
            })
            ->action(function (array $data, array $arguments, Action $action): void {
                $lesson = $this->lessonFor($arguments['lesson'] ?? 0);
                if (! $this->attempt($action, function () use ($lesson, $data): void {
                    DB::transaction(function () use ($lesson, $data): void {
                        app(ManageLessons::class)->update($this->actor(), $lesson, $this->lessonData($data));
                        $this->saveMedia($lesson, $data);
                    });
                })) {
                    return;
                }
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
            TextInput::make('duration_minutes')->label(__('curriculum.duration_minutes'))->numeric()->minValue(0)->maxValue(1440)->helperText(__('curriculum.duration_help')),
            Textarea::make('description.ar')->label(__('curriculum.description').' (AR)')->rows(2)->maxLength(5000)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            Textarea::make('description.en')->label(__('curriculum.description').' (EN)')->rows(2)->maxLength(5000)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            Toggle::make('is_preview')->label(__('curriculum.is_preview')),
            Select::make('media_provider')->label(__('curriculum.media_source'))->live()->placeholder(__('curriculum.media_keep'))
                ->options(static fn (Get $get): array => self::providerOptions((string) ($get('type') ?? 'video'))),
            FileUpload::make('media_file')->label(__('curriculum.media_file'))->helperText(__('curriculum.media_file_help'))
                ->disk(Config::string('media.disk'))->directory('media/uploads')->visibility('private')->maxSize(Config::integer('media.max_kilobytes'))
                ->extraInputAttributes(['accept' => implode(',', self::uploadMimes())])->preserveFilenames(false)
                ->visible(static fn (Get $get): bool => in_array($get('media_provider'), self::UPLOAD_PROVIDERS, true)),
            TextInput::make('media_url')->label(__('curriculum.media_link'))->url()->maxLength(2048)->extraInputAttributes(['dir' => 'ltr'])
                ->visible(static fn (Get $get): bool => in_array($get('media_provider'), self::LINK_PROVIDERS, true)),
            Textarea::make('media_text')->label(__('curriculum.media_text'))->rows(5)->maxLength(20000)
                ->visible(static fn (Get $get): bool => $get('media_provider') === 'rich_text'),
        ]));
    }

    /** Runs the save; a refused source is shown to the author and keeps the dialog open. Returns whether it succeeded. */
    private function attempt(Action $action, Closure $save): bool
    {
        try {
            $save();

            return true;
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
            $action->halt();

            return false;
        }
    }

    private const array UPLOAD_PROVIDERS = ['signed_storage', 'audio', 'document', 'image'];

    private const array LINK_PROVIDERS = ['vimeo', 'youtube', 'external_url', 'iframe'];

    /** @return array<string, string> media sources that make sense for a lesson type */
    private static function providerOptions(string $type): array
    {
        $keys = match ($type) {
            'audio' => ['audio'],
            'document' => ['document'],
            'text' => ['rich_text', 'image'],
            default => ['signed_storage', 'vimeo', 'youtube', 'external_url', 'iframe'],
        };

        return array_combine($keys, array_map(static fn (string $key): string => __("curriculum.sources.{$key}"), $keys));
    }

    /** @return list<string> */
    private static function uploadMimes(): array
    {
        $mimes = [];
        foreach (self::UPLOAD_PROVIDERS as $key) {
            $provider = MediaProviders::for($key);
            if ($provider instanceof PrivateFileProvider) {
                $mimes = [...$mimes, ...$provider->mimes()];
            }
        }

        return array_values(array_unique($mimes));
    }

    /**
     * Stores or attaches the media chosen in the lesson form. Leaving the source empty keeps the current media.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveMedia(Lesson $lesson, array $data): void
    {
        $provider = $data['media_provider'] ?? null;
        if (! is_string($provider) || $provider === '') {
            return;
        }

        $duration = $lesson->duration_seconds;
        if (in_array($provider, self::UPLOAD_PROVIDERS, true)) {
            $stored = $data['media_file'] ?? null;
            $stored = is_array($stored) ? (reset($stored) ?: null) : $stored;
            if (! is_string($stored) || $stored === '') {
                return;
            }

            $disk = Storage::disk(Config::string('media.disk'));
            try {
                app(UploadLessonMedia::class)($this->actor(), $lesson, $provider, new File($disk->path($stored)), basename($stored), $duration);
            } finally {
                $disk->delete($stored); // the upload staging copy is never kept
            }

            return;
        }

        $source = ['provider' => $provider, 'duration_seconds' => $duration];
        if ($provider === 'rich_text') {
            $source['text'] = $data['media_text'] ?? '';
        } else {
            $source['url'] = $data['media_url'] ?? '';
        }
        app(AttachLessonMedia::class)($this->actor(), $lesson, $source);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function lessonData(array $data): array
    {
        $minutes = $data['duration_minutes'] ?? null;

        return [
            'title' => $data['title'] ?? [], 'description' => $data['description'] ?? [], 'type' => $data['type'] ?? 'video', 'is_preview' => (bool) ($data['is_preview'] ?? false),
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
