<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Ingestion\KeyTermExtractor;
use App\Domain\Audit\AuditLogger;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentChunk;
use App\Models\LessonDocumentPage;
use App\Models\LessonKeyTerm;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The last stage of the pipeline (AI spec 6.5 to 6.7). A person approves the extracted text: it becomes the only text
 * the assistant may read, chunks and embeddings are built only above the full-context size, key terms are proposed,
 * and the version becomes READY and active while the previous one is retired in the same transaction.
 */
final class ApproveCurriculumDocument
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AiProviderInterface $ai,
        private readonly KeyTermExtractor $terms,
    ) {}

    public function __invoke(User $approver, LessonCurriculumDocument $document): LessonCurriculumDocument
    {
        if ($approver->getAttribute('status') !== 'active' || ! $approver->can('lesson_document.approve')) {
            throw new AuthorizationException;
        }

        $fresh = LessonCurriculumDocument::query()->findOrFail($document->getKey());
        $this->assertApprovable($fresh);

        // Embeddings are a network call to the provider, so they are prepared before any lock is taken.
        $texts = $this->approvedTexts($fresh);
        $chunks = $this->plan($texts);

        return DB::transaction(function () use ($approver, $document, $texts, $chunks): LessonCurriculumDocument {
            Lesson::query()->whereKey($document->lesson_id)->lockForUpdate()->first();
            $current = LessonCurriculumDocument::query()->lockForUpdate()->findOrFail($document->getKey());
            $this->assertApprovable($current);

            $current->forceFill(['status' => LessonCurriculumDocument::STATUS_INDEXING])->save();

            foreach ($texts as $pageNo => $text) {
                LessonDocumentPage::query()->where('document_id', $current->getKey())->where('page_no', $pageNo)->update(['approved_text' => $text]);
            }

            LessonDocumentChunk::query()->where('document_id', $current->getKey())->delete();
            foreach ($chunks as $chunk) {
                LessonDocumentChunk::query()->create(['document_id' => $current->getKey()] + $chunk);
            }

            $this->proposeKeyTerms($current, $texts);

            $retired = LessonCurriculumDocument::query()->where('lesson_id', $current->lesson_id)->where('active', true)->whereKeyNot($current->getKey())->pluck('id')->all();
            LessonCurriculumDocument::query()->whereKey($retired)->update(['active' => false]);
            $current->forceFill(['status' => LessonCurriculumDocument::STATUS_READY, 'active' => true, 'approved_by' => $approver->getKey(), 'approved_at' => now()])->save();

            if ($this->audit->record('curriculum_document.approved', $current->lesson, (int) $approver->getKey(), null, ['version' => $current->version, 'retired' => count($retired), 'chunked' => $chunks !== []]) === null) {
                throw new RuntimeException('Unable to record curriculum document audit.');
            }

            return $current;
        });
    }

    private function assertApprovable(LessonCurriculumDocument $document): void
    {
        if ($document->status === LessonCurriculumDocument::STATUS_FAILED) {
            throw ValidationException::withMessages(['document' => __('media.pdf_failed_version')]);
        }

        if ($document->status !== LessonCurriculumDocument::STATUS_AWAITING_APPROVAL) {
            throw ValidationException::withMessages(['document' => __('media.pdf_not_ready_for_approval')]);
        }
    }

    /** @return array<int, string> page number => approved text (edited text wins, otherwise the extraction) */
    private function approvedTexts(LessonCurriculumDocument $document): array
    {
        $texts = [];
        foreach ($document->documentPages as $page) {
            $texts[$page->page_no] = trim((string) ($page->approved_text ?? $page->raw_text));
        }

        return $texts;
    }

    /**
     * @param  array<int, string>  $texts
     * @return list<array{page_from: int, page_to: int, text: string, embedding: string|null}>
     */
    private function plan(array $texts): array
    {
        $tokens = (int) ceil(mb_strlen(implode("\n", $texts)) / 4);
        if ($tokens <= (int) config('ai.full_context_max_tokens', 40000)) {
            return [];
        }

        $size = max(1, (int) config('ai.ingestion.chunk_tokens', 800)) * 4;
        $chunks = [];
        $buffer = '';
        $from = $to = null;
        foreach ($texts as $pageNo => $text) {
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($text) > $size) {
                $chunks[] = ['page_from' => (int) $from, 'page_to' => (int) $to, 'text' => $buffer, 'embedding' => null];
                $buffer = '';
                $from = null;
            }
            $from ??= $pageNo;
            $to = $pageNo;
            $buffer .= ($buffer === '' ? '' : "\n").$text;
        }
        if ($buffer !== '') {
            $chunks[] = ['page_from' => (int) $from, 'page_to' => (int) $to, 'text' => $buffer, 'embedding' => null];
        }

        try {
            $vectors = $this->ai->embed(array_column($chunks, 'text'));
        } catch (Throwable) {
            throw ValidationException::withMessages(['document' => __('media.pdf_index_unavailable')]);
        }

        foreach ($chunks as $i => $chunk) {
            $chunks[$i]['embedding'] = json_encode($vectors[$i] ?? [], JSON_THROW_ON_ERROR);
        }

        return $chunks;
    }

    /** @param  array<int, string>  $texts */
    private function proposeKeyTerms(LessonCurriculumDocument $document, array $texts): void
    {
        LessonKeyTerm::query()->where('document_id', $document->getKey())->delete();
        $all = implode("\n", $texts);
        $locale = $this->terms->localeOf($all);
        foreach ($this->terms->extract($all) as $term) {
            LessonKeyTerm::query()->create(['document_id' => $document->getKey(), 'term' => $term, 'locale' => $locale, 'approved' => false]);
        }
    }
}
