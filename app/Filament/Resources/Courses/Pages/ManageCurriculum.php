<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\Assignments\Actions\ConfigureAssignment;
use App\Domain\Assignments\Actions\ManageAssignmentAttachments;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\MoveCurriculumItem;
use App\Domain\Curriculum\Actions\ReorderCurriculum;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Library\Actions\AttachLibraryItem;
use App\Domain\Library\LibraryAccess;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Actions\ManageLessonResources;
use App\Domain\Media\Actions\UploadCurriculumDocument;
use App\Domain\Media\Actions\UploadLessonMedia;
use App\Domain\Media\MediaProviders;
use App\Domain\Media\Providers\PrivateFileProvider;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\Concerns\ManagesLiveSessions;
use App\Filament\Shared\Forms\TranslatableTabs;
use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LibraryItem;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
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
    use ManagesLiveSessions;

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
            $this->addAssessmentAction(),
            $this->scheduleLiveSessionAction(),
            $this->addSectionAction(),
            Action::make('viewAsLearner')->label(__('curriculum.view_as_learner'))->icon('heroicon-o-play-circle')->color('gray')
                ->visible(fn (): bool => $this->firstPreviewItemId() !== null)
                ->url(fn (): string => route('learn.preview', ['course' => $this->course()->getKey(), 'item' => $this->firstPreviewItemId()]), shouldOpenInNewTab: true),
            Action::make('studentPreview')->label(__('curriculum.student_preview'))->icon('heroicon-o-eye')->color('gray')
                ->action(function (): void {
                    $this->previewing = ! $this->previewing;
                }),
        ];
    }

    private function firstPreviewItemId(): ?int
    {
        $item = CurriculumOutline::forManager($this->course())->flatMap(static fn (CourseSection $section) => $section->items)->first();

        return $item instanceof CurriculumItem ? (int) $item->getKey() : null;
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
                    'content' => $lesson->content,
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

    public function addAssessmentAction(): Action
    {
        return Action::make('addAssessment')->label(__('curriculum.add_assessment'))->icon('heroicon-o-clipboard-document-check')->color('gray')
            ->schema([
                Select::make('kind')->label(__('curriculum.assessment_kind'))->options([
                    'quiz' => __('curriculum.types.quiz'), 'assignment' => __('curriculum.types.assignment'),
                ])->required()->native(false),
                Select::make('section_id')->label(__('curriculum.section'))->required()->native(false)
                    ->options(fn (): array => CourseSection::query()->where('course_id', $this->course()->getKey())->whereNull('archived_at')->orderBy('sort')->get()->mapWithKeys(fn (CourseSection $s): array => [$s->getKey() => (string) $s->title])->all()),
                TextInput::make('title.ar')->label(__('curriculum.lesson_title').' (AR)')->maxLength(150)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                TextInput::make('title.en')->label(__('curriculum.lesson_title').' (EN)')->maxLength(150)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            ])
            ->action(function (array $data, Action $action): void {
                $section = CourseSection::query()->where('course_id', $this->course()->getKey())->whereNull('archived_at')->findOrFail($data['section_id']);
                if (! $this->attempt($action, fn () => app(ManageAssessmentItems::class)->add($this->actor(), $section, (string) $data['kind'], ['title' => $data['title'] ?? []]))) {
                    return;
                }
                Notification::make()->title(__('curriculum.saved'))->success()->send();
            });
    }

    public function configureAssignmentAction(): Action
    {
        return Action::make('configureAssignment')->label(__('curriculum.assignment.configure'))->modalHeading(__('curriculum.assignment.settings'))->modalWidth('3xl')
            ->schema([
                TranslatableTabs::make(static fn (string $locale): array => [
                    Textarea::make("instructions.{$locale}")->label(__('curriculum.assignment.instructions').' ('.strtoupper($locale).')')->rows(5)->maxLength(10000)
                        ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale]),
                ]),
                DateTimePicker::make('due_at')->label(__('curriculum.assignment.due_at'))->helperText(__('curriculum.assignment.due_help', ['zone' => $this->assignmentZone()]))->seconds(false),
                CheckboxList::make('allowed_types')->label(__('curriculum.assignment.allowed_types'))->required()->minItems(1)->columns(3)
                    ->options(array_combine(Assignment::TYPES, array_map(static fn (string $type): string => __("curriculum.assignment.types.{$type}"), Assignment::TYPES))),
                TextInput::make('max_size_mb')->label(__('curriculum.assignment.max_size'))->helperText(__('curriculum.assignment.max_size_help'))->numeric()->minValue(1)
                    ->maxValue(intdiv(Config::integer('assignments.ceiling_kilobytes'), 1024)),
                Select::make('late_policy')->label(__('curriculum.assignment.late_policy'))->required()->native(false)->live()->default(Assignment::LATE_NOT_ALLOWED)
                    ->options(array_combine(Assignment::LATE_POLICIES, array_map(static fn (string $policy): string => __("curriculum.assignment.late.{$policy}"), Assignment::LATE_POLICIES))),
                TextInput::make('late_penalty_percent')->label(__('curriculum.assignment.late_penalty'))->numeric()->minValue(1)->maxValue(100)->suffix('%')
                    ->visible(static fn (Get $get): bool => $get('late_policy') === Assignment::LATE_PENALTY)->required(static fn (Get $get): bool => $get('late_policy') === Assignment::LATE_PENALTY),
                TextInput::make('max_revisions')->label(__('curriculum.assignment.max_revisions'))->helperText(__('curriculum.assignment.max_revisions_help'))->numeric()->minValue(0)->maxValue(10)->default(0),
                Select::make('status')->label(__('curriculum.assignment.status'))->required()->native(false)
                    ->options([CurriculumItem::STATUS_DRAFT => __('curriculum.status_draft'), CurriculumItem::STATUS_PUBLISHED => __('curriculum.status_published')]),
                CheckboxList::make('remove_attachments')->label(__('curriculum.assignment.remove_attachments'))->columns(1)
                    ->options(fn (): array => $this->mountedAssignment()->attachments->mapWithKeys(static fn (AssignmentAttachment $a): array => [$a->getKey() => (string) $a->title])->all())
                    ->visible(fn (): bool => $this->mountedAssignment()->attachments->isNotEmpty()),
                FileUpload::make('attachment_file')->label(__('curriculum.assignment.attachment_file'))->helperText(__('curriculum.assignment.attachment_help', ['max' => ManageAssignmentAttachments::MAX]))
                    ->disk(Config::string('media.disk'))->directory('assignments/staging')->visibility('private')->maxSize(Config::integer('assignments.ceiling_kilobytes'))->preserveFilenames(false),
                TextInput::make('attachment_title.ar')->label(__('curriculum.assignment.attachment_title').' (AR)')->maxLength(150)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                TextInput::make('attachment_title.en')->label(__('curriculum.assignment.attachment_title').' (EN)')->maxLength(150)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            ])
            ->fillForm(function (array $arguments): array {
                $assignment = $this->assignmentFor($arguments['assignment'] ?? 0);

                return [
                    'instructions' => $assignment->getTranslations('instructions'),
                    'due_at' => $assignment->due_at?->copy()->setTimezone($this->assignmentZone())->format('Y-m-d H:i:s'),
                    'allowed_types' => $assignment->allowed_types ?? [], 'max_size_mb' => $assignment->max_size_kb === null ? null : (int) ceil($assignment->max_size_kb / 1024),
                    'late_policy' => $assignment->late_policy, 'late_penalty_percent' => $assignment->late_penalty_percent, 'max_revisions' => $assignment->max_revisions,
                    'status' => $this->assignmentItem($assignment)->status, 'remove_attachments' => [],
                ];
            })
            ->action(function (array $data, array $arguments, Action $action): void {
                $assignment = $this->assignmentFor($arguments['assignment'] ?? 0);
                if (! $this->attempt($action, function () use ($assignment, $data): void {
                    DB::transaction(function () use ($assignment, $data): void {
                        $actor = $this->actor();
                        app(ConfigureAssignment::class)($actor, $assignment, [
                            'instructions' => (array) ($data['instructions'] ?? []), 'due_at' => $data['due_at'] ?? null, 'timezone' => $this->assignmentZone(),
                            'allowed_types' => (array) ($data['allowed_types'] ?? []), 'late_policy' => $data['late_policy'] ?? Assignment::LATE_NOT_ALLOWED,
                            'max_size_kb' => filled($data['max_size_mb'] ?? null) ? (int) $data['max_size_mb'] * 1024 : null,
                            'late_penalty_percent' => filled($data['late_penalty_percent'] ?? null) ? (int) $data['late_penalty_percent'] : null,
                            'max_revisions' => filled($data['max_revisions'] ?? null) ? (int) $data['max_revisions'] : 0,
                        ]);
                        $item = $this->assignmentItem($assignment);
                        if ($item->status !== ($data['status'] ?? $item->status)) {
                            app(SetCurriculumStatus::class)($actor, $this->course(), [$item->getKey()], (string) $data['status']);
                        }
                        $this->saveAssignmentAttachments($assignment, $data);
                    });
                })) {
                    return;
                }
                Notification::make()->title(__('curriculum.saved'))->success()->send();
            });
    }

    /**
     * Removes the ticked attachments and adds the uploaded one. The staging copy Filament made is always deleted.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveAssignmentAttachments(Assignment $assignment, array $data): void
    {
        $manager = app(ManageAssignmentAttachments::class);
        foreach ((array) ($data['remove_attachments'] ?? []) as $id) {
            $attachment = $assignment->attachments()->find($id);
            if ($attachment instanceof AssignmentAttachment) {
                $manager->remove($this->actor(), $attachment);
            }
        }

        $stored = $data['attachment_file'] ?? null;
        $stored = is_array($stored) ? (reset($stored) ?: null) : $stored;
        if (! is_string($stored) || $stored === '') {
            return;
        }
        $disk = Storage::disk(Config::string('media.disk'));
        try {
            $title = array_filter((array) ($data['attachment_title'] ?? []), static fn ($v): bool => is_string($v) && trim($v) !== '');
            $manager->add($this->actor(), $assignment, new File($disk->path($stored)), $title);
        } finally {
            $disk->delete($stored);
        }
    }

    /** The assignment the open settings dialog belongs to (field closures do not receive the action arguments). */
    private function mountedAssignment(): Assignment
    {
        $arguments = $this->getMountedAction()?->getArguments() ?? [];

        return $this->assignmentFor($arguments['assignment'] ?? 0);
    }

    private function assignmentZone(): string
    {
        $zone = $this->actor()->getAttribute('timezone');

        return is_string($zone) && in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : 'UTC';
    }

    private function assignmentFor(int|string $id): Assignment
    {
        $item = CurriculumItem::query()->where('course_id', $this->course()->getKey())->where('itemable_type', 'assignment')->where('itemable_id', $id)->firstOrFail();
        $assignment = $item->itemable;
        abort_unless($assignment instanceof Assignment, 404);

        return $assignment;
    }

    private function assignmentItem(Assignment $assignment): CurriculumItem
    {
        $item = $assignment->item()->first();
        abort_unless($item instanceof CurriculumItem, 404);

        return $item;
    }

    public function archiveAssessmentAction(): Action
    {
        return Action::make('archiveAssessment')->label(__('curriculum.archive'))->color('danger')->requiresConfirmation()
            ->action(function (array $arguments): void {
                app(ManageAssessmentItems::class)->archive($this->actor(), $this->itemFor($arguments['item'] ?? 0));
                $this->selected = [];
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
            RichEditor::make('content')->label(__('curriculum.rich_content'))->helperText(__('curriculum.rich_content_help'))->json()
                ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3', 'h4'], ['bulletList', 'orderedList', 'blockquote', 'horizontalRule', 'table'], ['undo', 'redo']])
                ->visible(static fn (Get $get): bool => $get('type') === 'text'),
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
            Select::make('library_media')->label(__('library.pick_media'))->native(false)->searchable()->placeholder(__('library.none'))
                ->options(fn (Get $get): array => $this->libraryOptions(['video' => ['video'], 'audio' => ['audio'], 'document' => ['document'], 'text' => ['image']][$get('type') ?? 'video'] ?? []))
                ->visible(static fn (Get $get): bool => in_array($get('type') ?? 'video', ['video', 'audio', 'document'], true)),
            Select::make('library_resource')->label(__('library.pick_material'))->native(false)->searchable()->placeholder(__('library.none'))
                ->options(fn (): array => $this->libraryOptions(['document', 'file', 'image'])),
            Select::make('library_pdf')->label(__('library.pick_pdf'))->native(false)->searchable()->placeholder(__('library.none'))
                ->options(fn (): array => $this->libraryOptions(['document'])),
            FileUpload::make('curriculum_pdf')->label(__('curriculum.curriculum_pdf'))->helperText(__('curriculum.curriculum_pdf_help'))
                ->disk(Config::string('media.disk'))->directory('documents/uploads')->visibility('private')->maxSize(Config::integer('media.pdf_max_kilobytes'))
                ->extraInputAttributes(['accept' => 'application/pdf'])->preserveFilenames(false),
            FileUpload::make('resource_file')->label(__('curriculum.resource_file'))->helperText(__('curriculum.resource_file_help'))
                ->disk(Config::string('media.disk'))->directory('resources/uploads')->visibility('private')->maxSize(Config::integer('media.resource_max_kilobytes'))
                ->extraInputAttributes(['accept' => 'application/pdf,text/plain,image/png,image/jpeg,image/webp'])->preserveFilenames(false),
            TextInput::make('resource_title.ar')->label(__('curriculum.resource_title').' (AR)')->maxLength(150)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            TextInput::make('resource_title.en')->label(__('curriculum.resource_title').' (EN)')->maxLength(150)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            Toggle::make('resource_downloadable')->label(__('curriculum.resource_downloadable'))->default(true),
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

    /**
     * Curriculum PDF and lesson material from the lesson form. The staging copies Filament made are always removed.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveDocuments(Lesson $lesson, array $data): void
    {
        $disk = Storage::disk(Config::string('media.disk'));
        $stage = static function (mixed $value): ?string {
            $value = is_array($value) ? (reset($value) ?: null) : $value;

            return is_string($value) && $value !== '' ? $value : null;
        };

        $pdf = $stage($data['curriculum_pdf'] ?? null);
        if ($pdf !== null) {
            try {
                app(UploadCurriculumDocument::class)($this->actor(), $lesson, new File($disk->path($pdf)));
            } finally {
                $disk->delete($pdf);
            }
        }

        $resource = $stage($data['resource_file'] ?? null);
        if ($resource !== null) {
            try {
                $title = array_filter((array) ($data['resource_title'] ?? []), static fn ($v): bool => is_string($v) && trim($v) !== '');
                app(ManageLessonResources::class)->addFile($this->actor(), $lesson, new File($disk->path($resource)), [
                    'title' => $title === [] ? ['en' => basename($resource)] : $title, 'downloadable' => (bool) ($data['resource_downloadable'] ?? true),
                ]);
            } finally {
                $disk->delete($resource);
            }
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
        $this->saveDocuments($lesson, $data);
        $this->saveLibrary($lesson, $data);

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
            'content' => ($data['type'] ?? null) === 'text' ? ($data['content'] ?? null) : null,
        ];
    }

    /**
     * @param  list<string>  $kinds
     * @return array<int, string> visible library items of the given kinds, by id
     */
    private function libraryOptions(array $kinds): array
    {
        return LibraryAccess::visibleTo($this->actor())->whereIn('kind', $kinds)->orderByDesc('id')->limit(200)->get()
            ->mapWithKeys(static fn (LibraryItem $item): array => [$item->getKey() => (string) $item->title])->all();
    }

    /** @param  array<string, mixed>  $data */
    private function saveLibrary(Lesson $lesson, array $data): void
    {
        $attach = app(AttachLibraryItem::class);
        $find = fn (mixed $id): ?\App\Models\LibraryItem => filled($id) ? LibraryAccess::visibleTo($this->actor())->find($id) : null;

        if ($item = $find($data['library_media'] ?? null)) {
            $attach->asMedia($this->actor(), $item, $lesson, $lesson->duration_seconds);
        }
        if ($item = $find($data['library_resource'] ?? null)) {
            $attach->asResource($this->actor(), $item, $lesson);
        }
        if ($item = $find($data['library_pdf'] ?? null)) {
            $attach->asCurriculumPdf($this->actor(), $item, $lesson);
        }
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
