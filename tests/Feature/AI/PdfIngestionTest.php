<?php

declare(strict_types=1);

use App\Domain\AI\Actions\IngestCurriculumDocument;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Ingestion\ArabicTextRepair;
use App\Domain\AI\Ingestion\Contracts\PageRenderer;
use App\Domain\AI\Ingestion\Contracts\PdfTextExtractor;
use App\Domain\AI\Ingestion\DocumentReadiness;
use App\Domain\AI\Ingestion\IngestionFailure;
use App\Domain\AI\Ingestion\QualityScorer;
use App\Domain\AI\PromptRenderer;
use App\Domain\AI\Providers\NullAiProvider;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Media\Actions\ApproveCurriculumDocument;
use App\Domain\Media\Actions\ApproveKeyTerms;
use App\Domain\Media\Actions\EditExtractedPage;
use App\Domain\Media\Actions\UploadCurriculumDocument;
use App\Jobs\ProcessCurriculumDocument;
use App\Models\AiPromptVersion;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentChunk;
use App\Models\LessonKeyTerm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\PdfFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

/** @param list<string> $pages */
function ingExtractor(array $pages): void
{
    App::instance(PdfTextExtractor::class, new class($pages) implements PdfTextExtractor
    {
        /** @param list<string> $pages */
        public function __construct(private array $pages) {}

        public function extract(string $absolutePath): array
        {
            return $this->pages;
        }
    });
}

function ingRenderer(bool $works): void
{
    App::instance(PageRenderer::class, new class($works) implements PageRenderer
    {
        public function __construct(private bool $works) {}

        public function render(string $absolutePath, int $page): ?string
        {
            if (! $this->works) {
                return null;
            }
            $path = tempnam(sys_get_temp_dir(), 'ocr');
            file_put_contents($path, 'png');

            return $path;
        }
    });
}

/** @return array{0: User, 1: Lesson, 2: LessonCurriculumDocument} */
function ingDocument(int $pages = 3): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Ing'], 'slug_ar' => 'ing-'.uniqid(), 'slug_en' => 'ing-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'L']]);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    Storage::disk('local')->put('documents/'.$lesson->id.'.pdf', '%PDF-fake');
    $document = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id, 'version' => 1, 'path' => 'documents/'.$lesson->id.'.pdf', 'sha256' => hash('sha256', uniqid()), 'size' => 9,
        'pages' => $pages, 'status' => LessonCurriculumDocument::STATUS_UPLOADED, 'active' => false,
    ]);

    return [$instructor, $lesson, $document];
}

function ingGoodPage(string $topic): string
{
    return "Lesson about {$topic}. The teacher explains {$topic} step by step with a clear example and a short exercise.";
}

it('repairs Arabic presentation forms to base letters and strips invisible characters', function (): void {
    $forms = "\u{FE8D}\u{FEDF}\u{FEDC}\u{FE98}\u{FE8E}\u{FE8F}"; // the word الكتاب written in presentation forms

    $repaired = app(ArabicTextRepair::class)->repair("\u{200F}{$forms}\u{0007} \u{200B}\u{FEFF}الجديد");

    expect($repaired)->toBe('الكتاب الجديد')
        ->and(preg_match('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $repaired))->toBe(0);
})->group('T-P3-09', 'A1', 'ED-011');

it('turns visual-order lines back and leaves normal lines and numbers alone', function (): void {
    $logical = 'الكتاب الجديد المفيد عدد 125';
    // A PDF stores such a line in visual order: letters right to left, but each number keeps its own digit order.
    $visual = (string) preg_replace_callback('/\d+/u', fn (array $m): string => strrev($m[0]), implode('', array_reverse(preg_split('//u', $logical, -1, PREG_SPLIT_NO_EMPTY))));
    $repair = app(ArabicTextRepair::class);

    expect($repair->repair($visual))->toBe($logical)
        ->and($repair->repair($logical))->toBe($logical)
        ->and($repair->repair('Plain English line 2026'))->toBe('Plain English line 2026');
})->group('T-P3-09', 'A1', 'ED-011');

it('removes headers and footers repeated across pages, including changing page numbers', function (): void {
    $pages = [];
    foreach ([1, 2, 3, 4] as $n) {
        $pages[] = "Young Go Academy\nUnique body number {$n} with enough words\nPage {$n}";
    }

    $clean = app(ArabicTextRepair::class)->stripRepeatedLines($pages);

    expect($clean[0])->toBe('Unique body number 1 with enough words')->and($clean[3])->toBe('Unique body number 4 with enough words');
    expect(app(ArabicTextRepair::class)->stripRepeatedLines(array_slice($pages, 0, 2)))->toBe(array_slice($pages, 0, 2)); // too few pages to judge
})->group('T-P3-09', 'A1');

it('scores readable text high and broken text low', function (): void {
    $scorer = app(QualityScorer::class);

    expect($scorer->score('This is a clear readable sentence about colour mixing.'))->toBeGreaterThan(90.0)
        ->and($scorer->score('هذا درس واضح عن مزج الألوان الأساسية'))->toBeGreaterThan(90.0)
        ->and($scorer->score("\u{FE8D}\u{FEDF}\u{FEDC}\u{FE98}\u{FE8E}\u{FE8F} \u{FE8D}\u{FEDF}\u{FEDC}\u{FE98}"))->toBeLessThan(40.0)
        ->and($scorer->score(''))->toBe(0.0)
        ->and($scorer->score('Readable enough text here for scoring', 0.5))->toBeLessThan(55.0);
})->group('T-P3-09', 'A1');

it('extracts a good PDF into pages awaiting approval and activates nothing', function (): void {
    [, $lesson, $document] = ingDocument(3);
    ingExtractor([ingGoodPage('colours'), ingGoodPage('shapes'), ingGoodPage('shading')]);

    $result = app(IngestCurriculumDocument::class)($document);

    expect($result->status)->toBe(LessonCurriculumDocument::STATUS_AWAITING_APPROVAL)->and($result->active)->toBeFalse()
        ->and((float) $result->quality_score)->toBeGreaterThan(90.0)
        ->and($result->documentPages)->toHaveCount(3)
        ->and($result->documentPages->first()->approved_text)->toBeNull()
        ->and($lesson->activeCurriculumDocument())->toBeNull()
        ->and(DocumentReadiness::forLesson($lesson))->toBe(DocumentReadiness::AwaitingApproval);
})->group('T-P3-09', 'A3');

it('sends pages without a text layer to OCR and flags them', function (): void {
    [, , $document] = ingDocument(3);
    ingExtractor([ingGoodPage('colours'), '', ingGoodPage('shading')]);
    ingRenderer(true);

    $result = app(IngestCurriculumDocument::class)($document);
    $scanned = $result->documentPages->firstWhere('page_no', 2);

    expect($result->status)->toBe(LessonCurriculumDocument::STATUS_AWAITING_APPROVAL)
        ->and($scanned->ocr_used)->toBeTrue()->and($scanned->raw_text)->toContain('FAKE OCR TEXT')
        ->and($result->documentPages->firstWhere('page_no', 1)->ocr_used)->toBeFalse()
        ->and(app(AiProviderInterface::class)->health()->healthy)->toBeTrue();
})->group('T-P3-09', 'A2');

it('uses OCR for a long text layer without readable words', function (): void {
    [, , $document] = ingDocument(1);
    ingExtractor([str_repeat('... ', 30)]);
    ingRenderer(true);

    $result = app(IngestCurriculumDocument::class)($document);

    expect($result->status)->toBe(LessonCurriculumDocument::STATUS_AWAITING_APPROVAL)
        ->and($result->documentPages->sole()->ocr_used)->toBeTrue();
})->group('T-P3-09', 'A2');

it('marks the document failed when the uploaded file is missing', function (): void {
    [, , $document] = ingDocument(1);
    Storage::disk('local')->delete($document->path);

    expect(app(IngestCurriculumDocument::class)($document)->status)->toBe(LessonCurriculumDocument::STATUS_FAILED);
})->group('T-P3-09', 'A2');

it('fails with a readable reason when pages stay unreadable', function (): void {
    [, $lesson, $document] = ingDocument(4);
    ingExtractor([ingGoodPage('colours'), '', '', ingGoodPage('shading')]);
    ingRenderer(false);

    $result = app(IngestCurriculumDocument::class)($document);

    expect($result->status)->toBe(LessonCurriculumDocument::STATUS_FAILED)->and($result->failure_reason)->toBe('unreadable_pages:2-3')
        ->and(IngestionFailure::describe($result->failure_reason))->toContain('2-3')
        ->and(DocumentReadiness::forLesson($lesson))->toBe(DocumentReadiness::Failed);

    app()->setLocale('ar');
    expect(IngestionFailure::describe($result->failure_reason))->toContain('الصفحات 2-3');
})->group('T-P3-09', 'A2');

it('fails when OCR is needed but no provider is configured, and when the extractor is missing', function (): void {
    [, , $document] = ingDocument(2);
    ingExtractor([ingGoodPage('colours'), '']);
    ingRenderer(true);
    App::instance(AiProviderInterface::class, new NullAiProvider);

    expect(app(IngestCurriculumDocument::class)($document)->failure_reason)->toBe('unreadable_pages:2');

    $document->update(['status' => LessonCurriculumDocument::STATUS_UPLOADED]);
    App::instance(PdfTextExtractor::class, new class implements PdfTextExtractor
    {
        public function extract(string $absolutePath): array
        {
            throw new RuntimeException('pdftotext missing');
        }
    });
    $result = app(IngestCurriculumDocument::class)($document->refresh());

    expect($result->status)->toBe(LessonCurriculumDocument::STATUS_FAILED)->and($result->failure_reason)->toBe('extractor_unavailable');
})->group('T-P3-09', 'A2');

it('only lets an approver approve, and only after extraction', function (): void {
    [$instructor, , $document] = ingDocument(2);
    $admin = User::factory()->create()->assignRole('admin');

    expect(fn () => app(ApproveCurriculumDocument::class)($admin, $document))->toThrow(ValidationException::class); // still uploaded

    ingExtractor([ingGoodPage('colours'), ingGoodPage('shapes')]);
    app(IngestCurriculumDocument::class)($document);

    foreach ([$instructor, User::factory()->create()->assignRole('student')] as $actor) {
        expect(fn () => app(ApproveCurriculumDocument::class)($actor, $document->refresh()))->toThrow(AuthorizationException::class);
        expect(fn () => app(EditExtractedPage::class)($actor, $document->refresh(), 1, 'x'))->toThrow(AuthorizationException::class);
    }
})->group('T-P3-09', 'A3');

it('makes the approved edited text the only active source and swaps versions atomically', function (): void {
    [, $lesson, $v1] = ingDocument(2);
    $admin = User::factory()->create()->assignRole('admin');
    ingExtractor([ingGoodPage('colours'), ingGoodPage('shapes')]);
    app(IngestCurriculumDocument::class)($v1);

    app(EditExtractedPage::class)($admin, $v1->refresh(), 1, 'Corrected text about colours and mixing.');
    app(ApproveCurriculumDocument::class)($admin, $v1->refresh());

    $v1->refresh();
    expect($v1->status)->toBe('ready')->and($v1->active)->toBeTrue()->and($lesson->activeCurriculumDocument()->id)->toBe($v1->id)
        ->and($v1->documentPages->firstWhere('page_no', 1)->approved_text)->toBe('Corrected text about colours and mixing.')
        ->and($v1->documentPages->firstWhere('page_no', 2)->approved_text)->toBe(ingGoodPage('shapes'))
        ->and(LessonDocumentChunk::query()->count())->toBe(0); // small document: full-context mode, no chunks

    // Replacement: the old version stays active until the new one is approved, then is retired in one step.
    $v2 = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id, 'version' => 2, 'path' => $v1->path, 'sha256' => hash('sha256', 'v2'), 'size' => 9, 'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_UPLOADED, 'active' => false,
    ]);
    ingExtractor([ingGoodPage('lines'), ingGoodPage('depth')]);
    app(IngestCurriculumDocument::class)($v2);
    expect($lesson->activeCurriculumDocument()->id)->toBe($v1->id);

    app(ApproveCurriculumDocument::class)($admin, $v2->refresh());
    expect($lesson->activeCurriculumDocument()->id)->toBe($v2->id)->and($v1->refresh()->active)->toBeFalse()
        ->and(LessonCurriculumDocument::query()->where('lesson_id', $lesson->id)->where('active', true)->count())->toBe(1);

    expect(fn () => app(EditExtractedPage::class)($admin, $v2->refresh(), 1, 'late edit'))->toThrow(ValidationException::class);
    expect(fn () => app(ApproveCurriculumDocument::class)($admin, $v2->refresh()))->toThrow(ValidationException::class);
})->group('T-P3-09', 'A3');

it('rejects empty or oversized page edits', function (): void {
    [, , $document] = ingDocument(2);
    $admin = User::factory()->create()->assignRole('admin');
    ingExtractor([ingGoodPage('colours'), ingGoodPage('shapes')]);
    app(IngestCurriculumDocument::class)($document);

    expect(fn () => app(EditExtractedPage::class)($admin, $document->refresh(), 1, '   '))->toThrow(ValidationException::class)
        ->and(fn () => app(EditExtractedPage::class)($admin, $document->refresh(), 1, str_repeat('a', 20001)))->toThrow(ValidationException::class)
        ->and(fn () => app(EditExtractedPage::class)($admin, $document->refresh(), 9, 'valid text'))->toThrow(ModelNotFoundException::class);
})->group('T-P3-09', 'A3');

it('preserves each page correction with its author and previous text', function (): void {
    [, , $document] = ingDocument(1);
    $admin = User::factory()->create()->assignRole('admin');
    $original = ingGoodPage('colours');
    ingExtractor([$original]);
    app(IngestCurriculumDocument::class)($document);

    app(EditExtractedPage::class)($admin, $document, 1, 'First correction.');
    app(EditExtractedPage::class)($admin, $document, 1, 'Second correction.');

    $page = $document->documentPages()->sole();
    $this->assertDatabaseHas('lesson_document_page_revisions', [
        'page_id' => $page->getKey(), 'edited_by' => $admin->id,
        'previous_text' => $original, 'text' => 'First correction.',
    ]);
    $this->assertDatabaseHas('lesson_document_page_revisions', [
        'page_id' => $page->getKey(), 'edited_by' => $admin->id,
        'previous_text' => 'First correction.', 'text' => 'Second correction.',
    ]);
})->group('T-P3-09', 'A3');

it('keeps the newer approved source active when an older version is still awaiting review', function (): void {
    [, $lesson, $older] = ingDocument(1);
    $admin = User::factory()->create()->assignRole('admin');
    $newer = $older->replicate()->fill(['version' => 2, 'sha256' => hash('sha256', 'replacement')]);
    $newer->save();
    ingExtractor([ingGoodPage('colours')]);
    app(IngestCurriculumDocument::class)($older);
    app(IngestCurriculumDocument::class)($newer);
    app(ApproveCurriculumDocument::class)($admin, $newer);

    expect(fn () => app(ApproveCurriculumDocument::class)($admin, $older))->toThrow(ValidationException::class)
        ->and($lesson->activeCurriculumDocument()->getKey())->toBe($newer->getKey());
})->group('T-P3-09', 'A3');

it('preserves an edit saved while embeddings are being prepared', function (): void {
    config(['ai.full_context_max_tokens' => 1]);
    [, , $document] = ingDocument(1);
    $admin = User::factory()->create()->assignRole('admin');
    ingExtractor([ingGoodPage('colours')]);
    app(IngestCurriculumDocument::class)($document);

    $provider = Mockery::mock(AiProviderInterface::class);
    $provider->shouldReceive('embed')->once()->andReturnUsing(function () use ($admin, $document): array {
        app(EditExtractedPage::class)($admin, $document, 1, 'A newer review edit.');

        return [[0.1, 0.2]];
    });
    App::instance(AiProviderInterface::class, $provider);

    expect(fn () => app(ApproveCurriculumDocument::class)($admin, $document))->toThrow(ValidationException::class)
        ->and($document->refresh()->status)->toBe(LessonCurriculumDocument::STATUS_AWAITING_APPROVAL)
        ->and($document->documentPages()->sole()->approved_text)->toBe('A newer review edit.')
        ->and(LessonDocumentChunk::query()->count())->toBe(0);
})->group('T-P3-09', 'A3');

it('builds chunks and embeddings only above the full-context size', function (): void {
    config(['ai.full_context_max_tokens' => 20, 'ai.ingestion.chunk_tokens' => 30]);
    [, , $document] = ingDocument(3);
    $admin = User::factory()->create()->assignRole('admin');
    ingExtractor([ingGoodPage('colours'), ingGoodPage('shapes'), ingGoodPage('shading')]);
    app(IngestCurriculumDocument::class)($document);

    app(ApproveCurriculumDocument::class)($admin, $document->refresh());

    $chunks = LessonDocumentChunk::query()->orderBy('page_from')->get();
    expect($chunks->count())->toBeGreaterThan(1)->and($chunks->first()->page_from)->toBe(1)->and($chunks->last()->page_to)->toBe(3)
        ->and(json_decode((string) $chunks->first()->embedding, true))->toBeArray()->not->toBeEmpty();
})->group('T-P3-09', 'A3');

it('leaves the version waiting when chunked mode needs a provider that is not configured', function (): void {
    config(['ai.full_context_max_tokens' => 20, 'ai.ingestion.chunk_tokens' => 30]);
    [, $lesson, $document] = ingDocument(3);
    $admin = User::factory()->create()->assignRole('admin');
    ingExtractor([ingGoodPage('colours'), ingGoodPage('shapes'), ingGoodPage('shading')]);
    app(IngestCurriculumDocument::class)($document);
    App::instance(AiProviderInterface::class, new NullAiProvider);

    expect(fn () => app(ApproveCurriculumDocument::class)($admin, $document->refresh()))->toThrow(ValidationException::class)
        ->and($document->refresh()->status)->toBe(LessonCurriculumDocument::STATUS_AWAITING_APPROVAL)
        ->and($lesson->activeCurriculumDocument())->toBeNull();
})->group('T-P3-09', 'A3');

it('proposes key terms that appear in the text and lets the approver choose which learners see', function (): void {
    [, , $document] = ingDocument(2);
    $admin = User::factory()->create()->assignRole('admin');
    $text = 'Perspective drawing uses vanishing points. Perspective lines meet at the vanishing point, and shading adds depth. Shading follows the light source.';
    ingExtractor([$text, 'Contrast and shading make perspective drawing feel real. Light direction changes shading.']);
    app(IngestCurriculumDocument::class)($document);
    app(ApproveCurriculumDocument::class)($admin, $document->refresh());

    $terms = LessonKeyTerm::query()->where('document_id', $document->id)->get();
    expect($terms->count())->toBeBetween(3, 8)->and($terms->every(fn ($t) => ! $t->approved && str_contains(mb_strtolower($text.' contrast light'), $t->term)))->toBeTrue()
        ->and($terms->pluck('term')->first())->toBe('shading');

    $chosen = $terms->take(2)->pluck('id')->all();
    expect(app(ApproveKeyTerms::class)($admin, $document, $chosen))->toBe(2)
        ->and(LessonKeyTerm::query()->where('approved', true)->count())->toBe(2);
    expect(fn () => app(ApproveKeyTerms::class)(User::factory()->create()->assignRole('student'), $document, $chosen))->toThrow(AuthorizationException::class);
    expect(fn () => app(ApproveKeyTerms::class)($admin, $document, range(1, 9)))->toThrow(ValidationException::class);
})->group('T-P3-09', 'A3');

it('keeps no key terms when the text is too thin to offer three', function (): void {
    [, , $document] = ingDocument(1);
    $admin = User::factory()->create()->assignRole('admin');
    ingExtractor(['Short note about colour only here today.']);
    app(IngestCurriculumDocument::class)($document);
    app(ApproveCurriculumDocument::class)($admin, $document->refresh());

    expect(LessonKeyTerm::query()->count())->toBeLessThanOrEqual(8)->and($document->refresh()->status)->toBe('ready');
})->group('T-P3-09', 'A3');

it('stores injection text found inside a PDF as plain data and never lets it act', function (): void {
    $payload = (string) file_get_contents(base_path('tests/Fixtures/ai/redteam/pdf-injection.txt'));
    [, $lesson, $document] = ingDocument(1);
    $admin = User::factory()->create()->assignRole('admin');
    ingExtractor([$payload]);
    app(IngestCurriculumDocument::class)($document);

    $page = $document->refresh()->documentPages->first();
    expect($document->status)->toBe(LessonCurriculumDocument::STATUS_AWAITING_APPROVAL)->and($page->raw_text)->toContain('IGNORE ALL PREVIOUS INSTRUCTIONS')
        ->and($lesson->activeCurriculumDocument())->toBeNull();

    app(ApproveCurriculumDocument::class)($admin, $document);
    $prompt = app(PromptRenderer::class)->render(AiPromptVersion::current(), 'adults', 'English', 'Colours', 1, [1 => (string) $document->refresh()->documentPages->first()->approved_text]);

    expect(substr_count($prompt, '</lesson_source>'))->toBe(1)->and($prompt)->not->toContain('[[page 99]]');
})->group('T-P3-09', 'A4');

it('queues ingestion on upload and the job runs the pipeline', function (): void {
    config(['ai.ingestion.dispatch_on_upload' => true]);
    Queue::fake();
    [$instructor, $lesson] = ingDocument(1);
    $bytes = PdfFixture::bytes();
    $file = UploadedFile::fake()->createWithContent('lesson.pdf', $bytes);

    $document = app(UploadCurriculumDocument::class)($instructor, $lesson, $file);

    Queue::assertPushed(ProcessCurriculumDocument::class, fn ($job) => $job->documentId === $document->id);

    ingExtractor([ingGoodPage('colours')]);
    (new ProcessCurriculumDocument($document->id))->handle(app(IngestCurriculumDocument::class));
    expect($document->refresh()->status)->toBe(LessonCurriculumDocument::STATUS_AWAITING_APPROVAL);
    (new ProcessCurriculumDocument(999999))->handle(app(IngestCurriculumDocument::class)); // a vanished version is ignored
})->group('T-P3-09', 'A3');

it('shows the readiness state for every stage', function (string $status, DocumentReadiness $expected): void {
    [, $lesson, $document] = ingDocument();
    $document->update(['status' => $status]);

    expect(DocumentReadiness::forLesson($lesson))->toBe($expected)->and($expected->icon())->not->toBeEmpty()->and($expected->label())->not->toBeEmpty();
})->with([
    'uploaded' => ['uploaded', DocumentReadiness::Processing],
    'extracting' => ['extracting', DocumentReadiness::Processing],
    'indexing' => ['indexing', DocumentReadiness::Processing],
    'awaiting' => ['awaiting_approval', DocumentReadiness::AwaitingApproval],
    'failed' => ['failed', DocumentReadiness::Failed],
    'rejected' => ['rejected', DocumentReadiness::Failed],
    'ready' => ['ready', DocumentReadiness::Ready],
])->group('T-P3-09', 'A3');

it('shows no readiness when a lesson has no PDF', function (): void {
    [, $lesson, $document] = ingDocument();
    $document->delete();

    expect(DocumentReadiness::forLesson($lesson->refresh()))->toBe(DocumentReadiness::None);
})->group('T-P3-09', 'A3');
