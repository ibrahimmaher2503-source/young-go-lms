<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Pages;

use App\Domain\AI\Ingestion\DocumentReadiness;
use App\Domain\AI\Ingestion\IngestionFailure;
use App\Domain\Media\Actions\ApproveCurriculumDocument;
use App\Domain\Media\Actions\ApproveKeyTerms;
use App\Domain\Media\Actions\EditExtractedPage;
use App\Domain\Media\SignedFileUrl;
use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Approval screen for a curriculum PDF version (AI spec 6.5): the PDF beside the text that was extracted from it. The
 * approver can fix the text, approve it (which activates the version) and then choose the key terms learners see.
 */
final class ReviewCurriculumDocument extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.courses.review-curriculum-document';

    public int $documentId = 0;

    /** @var array<int, string> page number => text being edited */
    public array $texts = [];

    /** @var list<int> */
    public array $terms = [];

    public function mount(int|string $record, int|string $document): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(Gate::allows('update', $this->record) && $this->actor()->can('lesson_document.approve'), 403);

        $found = $this->loadDocument((int) $document);
        $this->documentId = (int) $found->getKey();
        $this->loadState($found);
    }

    public function getTitle(): string
    {
        return __('media.review_title');
    }

    public function getBreadcrumbs(): array
    {
        return [
            CourseResource::getUrl('index') => __('courses.title'),
            CourseResource::getUrl('curriculum', ['record' => $this->record]) => __('curriculum.curriculum'),
            '#' => __('media.review_title'),
        ];
    }

    public function savePage(int $pageNo): void
    {
        $this->attempt(function () use ($pageNo): void {
            app(EditExtractedPage::class)($this->actor(), $this->loadDocument($this->documentId), $pageNo, $this->texts[$pageNo] ?? '');
            Notification::make()->success()->title(__('media.page_saved'))->send();
        });
    }

    public function approve(): void
    {
        $this->attempt(function (): void {
            app(ApproveCurriculumDocument::class)($this->actor(), $this->loadDocument($this->documentId));
            $this->loadState($this->loadDocument($this->documentId));
            Notification::make()->success()->title(__('media.approved'))->send();
        });
    }

    public function saveTerms(): void
    {
        $this->attempt(function (): void {
            app(ApproveKeyTerms::class)($this->actor(), $this->loadDocument($this->documentId), array_map('intval', $this->terms));
            Notification::make()->success()->title(__('media.terms_saved'))->send();
        });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $document = $this->loadDocument($this->documentId);

        return [
            'document' => $document,
            'pages' => $document->documentPages,
            'terms_list' => $document->keyTerms,
            'readiness' => DocumentReadiness::fromStatus($document->status),
            'failure' => IngestionFailure::describe($document->failure_reason),
            'pdfUrl' => app(SignedFileUrl::class)->for($this->actor(), $document),
            'canApprove' => $document->status === LessonCurriculumDocument::STATUS_AWAITING_APPROVAL,
        ];
    }

    private function loadState(LessonCurriculumDocument $document): void
    {
        $this->texts = $document->documentPages->mapWithKeys(fn ($page): array => [$page->page_no => (string) ($page->approved_text ?? $page->raw_text)])->all();
        $this->terms = $document->keyTerms->where('approved', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    private function loadDocument(int $id): LessonCurriculumDocument
    {
        /** @var Course $course */
        $course = $this->record;
        $document = LessonCurriculumDocument::query()->with(['documentPages', 'keyTerms', 'lesson'])->findOrFail($id);
        abort_unless((int) $document->lesson->item()->value('course_id') === (int) $course->getKey(), 404);

        return $document;
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    private function attempt(callable $work): void
    {
        try {
            $work();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
        }
    }
}
