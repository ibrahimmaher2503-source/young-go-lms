<?php

declare(strict_types=1);

namespace App\Domain\AI\Ingestion;

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Ingestion\Contracts\PageRenderer;
use App\Domain\AI\Ingestion\Contracts\PdfTextExtractor;
use App\Domain\Audit\AuditLogger;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Runs the extraction half of the pipeline (AI spec 6.2 to 6.4) for one uploaded version: text layer, repair, OCR for
 * pages with no text, quality score. It ends in AWAITING_APPROVAL or FAILED with a reason. It never activates anything:
 * only a person approving the text does (ApproveCurriculumDocument). Text found inside the PDF is stored as data.
 */
final class IngestCurriculumDocument
{
    public function __construct(
        private readonly PdfTextExtractor $extractor,
        private readonly PageRenderer $renderer,
        private readonly AiProviderInterface $ai,
        private readonly ArabicTextRepair $repair,
        private readonly QualityScorer $scorer,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(LessonCurriculumDocument $document): LessonCurriculumDocument
    {
        if (! in_array($document->status, [LessonCurriculumDocument::STATUS_UPLOADED, LessonCurriculumDocument::STATUS_EXTRACTING, LessonCurriculumDocument::STATUS_FAILED], true)) {
            return $document;
        }

        $document->forceFill(['status' => LessonCurriculumDocument::STATUS_EXTRACTING, 'failure_reason' => null])->save();

        $local = $this->localCopy($document);
        try {
            $raw = $this->extractor->extract($local);
            $pages = $this->buildPages($raw, $document, $local);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail($document, 'extractor_unavailable');
        } finally {
            @unlink($local);
        }

        return $this->conclude($document, $pages);
    }

    private function localCopy(LessonCurriculumDocument $document): string
    {
        $stream = Storage::disk((string) config('media.disk', 'local'))->readStream($document->path);
        if ($stream === null) {
            throw new RuntimeException('The PDF is missing from private storage.');
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ingest-'.bin2hex(random_bytes(6)).'.pdf';
        $target = fopen($path, 'wb');
        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return $path;
    }

    /**
     * @param  list<string>  $raw
     * @return list<array{page_no: int, text: string, ocr: bool, score: float}>
     */
    private function buildPages(array $raw, LessonCurriculumDocument $document, string $local): array
    {
        $count = max($document->pages, count($raw));
        $texts = [];
        for ($i = 0; $i < $count; $i++) {
            $texts[] = $this->repair->repair($raw[$i] ?? '');
        }
        $texts = $this->repair->stripRepeatedLines($texts);

        $minChars = (int) config('ai.ingestion.min_page_chars', 20);
        $pages = [];
        foreach ($texts as $i => $text) {
            $ocr = false;
            $confidence = 1.0;
            if (mb_strlen($text) < $minChars) {
                [$text, $confidence] = $this->ocr($local, $i + 1) ?? [$text, 1.0];
                $ocr = mb_strlen($text) >= $minChars;
            }

            $pages[] = ['page_no' => $i + 1, 'text' => $text, 'ocr' => $ocr, 'score' => $this->scorer->score($text, $confidence)];
        }

        return $pages;
    }

    /** @return array{0: string, 1: float}|null */
    private function ocr(string $local, int $page): ?array
    {
        $image = $this->renderer->render($local, $page);
        if ($image === null) {
            return null;
        }

        try {
            $result = $this->ai->ocrPage($image, ['ar', 'en']);

            return [$this->repair->repair($result->text), $result->confidence];
        } catch (Throwable) {
            return null; // no provider configured or it failed: the page stays unreadable
        } finally {
            @unlink($image);
        }
    }

    /** @param  list<array{page_no: int, text: string, ocr: bool, score: float}>  $pages */
    private function conclude(LessonCurriculumDocument $document, array $pages): LessonCurriculumDocument
    {
        $minPage = (float) config('ai.ingestion.page_min_score', 60);
        $unreadable = array_values(array_map(
            static fn (array $page): int => $page['page_no'],
            array_filter($pages, static fn (array $page): bool => $page['score'] < $minPage),
        ));
        $average = $pages === [] ? 0.0 : round(array_sum(array_column($pages, 'score')) / count($pages), 2);

        $unreadableRatio = $pages === [] ? 1.0 : count($unreadable) / count($pages);

        $failure = match (true) {
            $unreadableRatio >= 1.0 => 'no_text',
            $unreadableRatio > (float) config('ai.ingestion.max_unreadable_ratio', 0.25) => 'unreadable_pages:'.IngestionFailure::ranges($unreadable),
            $average < (float) config('ai.ingestion.document_min_score', 70) => 'low_quality',
            default => null,
        };

        DB::transaction(function () use ($document, $pages, $average, $failure): void {
            LessonDocumentPage::query()->where('document_id', $document->getKey())->delete();
            foreach ($pages as $page) {
                LessonDocumentPage::query()->create([
                    'document_id' => $document->getKey(), 'page_no' => $page['page_no'], 'raw_text' => $page['text'],
                    'ocr_used' => $page['ocr'], 'quality_score' => $page['score'],
                ]);
            }
            $document->forceFill([
                'status' => $failure === null ? LessonCurriculumDocument::STATUS_AWAITING_APPROVAL : LessonCurriculumDocument::STATUS_FAILED,
                'failure_reason' => $failure, 'quality_score' => $average,
            ])->save();
        });

        $this->audit->record(
            $failure === null ? 'curriculum_document.extracted' : 'curriculum_document.extraction_failed',
            $document->lesson,
            null,
            null,
            ['version' => $document->version, 'score' => $average, 'ocr_pages' => count(array_filter($pages, static fn (array $p): bool => $p['ocr'])), 'failure' => $failure],
        );

        return $document->refresh();
    }

    private function fail(LessonCurriculumDocument $document, string $reason): LessonCurriculumDocument
    {
        $document->forceFill(['status' => LessonCurriculumDocument::STATUS_FAILED, 'failure_reason' => $reason])->save();
        $this->audit->record('curriculum_document.extraction_failed', $document->lesson, null, null, ['version' => $document->version, 'failure' => $reason]);

        return $document->refresh();
    }
}
