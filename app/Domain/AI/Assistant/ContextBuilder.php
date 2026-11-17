<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\UnavailableReason;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentChunk;
use App\Models\LessonDocumentPage;
use Throwable;

/**
 * Builds the lesson-PDF-only context (AI spec 5). Every query is keyed by the document the server resolved from the lesson,
 * so no other lesson's text can be reached. Approved text only: raw extraction is never a source.
 */
final class ContextBuilder
{
    public function __construct(private readonly AiProviderInterface $provider) {}

    public function build(LessonCurriculumDocument $document, string $question): AssistantContext
    {
        $all = [];
        foreach (LessonDocumentPage::query()->where('document_id', $document->getKey())->orderBy('page_no')->get() as $page) {
            $text = trim((string) $page->approved_text);
            if ($text !== '') {
                $all[$page->page_no] = $text;
            }
        }

        if ($all === []) {
            throw AssistantRefused::unavailable(UnavailableReason::NoApprovedDocument);
        }

        $tokens = (int) ceil(mb_strlen(implode("\n", $all)) / 4);
        if ($tokens <= (int) config('ai.full_context_max_tokens', 40000)) {
            return new AssistantContext(AssistantContext::FULL, $document->version, $all, $all);
        }

        return new AssistantContext(AssistantContext::CHUNKED, $document->version, $this->retrievePages($document, $question, $all), $all);
    }

    /**
     * @param  array<int, string>  $all
     * @return array<int, string>
     */
    private function retrievePages(LessonCurriculumDocument $document, string $question, array $all): array
    {
        $chunks = LessonDocumentChunk::query()->where('document_id', $document->getKey())->orderBy('id')->get()
            ->map(static fn (LessonDocumentChunk $chunk): array => ['from' => $chunk->page_from, 'to' => $chunk->page_to, 'text' => $chunk->text, 'embedding' => json_decode((string) $chunk->embedding, true)])
            ->all();

        if ($chunks === []) {
            // Large document without stored chunks: one chunk per page, keyword retrieval only.
            foreach ($all as $number => $text) {
                $chunks[] = ['from' => $number, 'to' => $number, 'text' => $text, 'embedding' => null];
            }
        }

        $query = array_values(array_unique(array_filter(TextNormalizer::words($question), static fn (string $word): bool => mb_strlen($word) > 1)));
        $vector = $this->questionVector($question);

        $scored = [];
        foreach ($chunks as $index => $chunk) {
            $keyword = $query === [] ? 0.0 : count(array_intersect($query, TextNormalizer::words($chunk['text']))) / count($query);
            $embedding = is_array($chunk['embedding']) ? $chunk['embedding'] : null;
            $semantic = $vector !== null && $embedding !== null ? $this->cosine($vector, $embedding) : null;
            $scored[$index] = $semantic === null ? $keyword : ($keyword + max(0.0, $semantic)) / 2;
        }

        arsort($scored);
        $pages = [];
        foreach (array_slice(array_keys($scored), 0, max(1, (int) config('ai.assistant.retrieval_top_k', 6))) as $index) {
            for ($number = $chunks[$index]['from']; $number <= $chunks[$index]['to']; $number++) {
                if (isset($all[$number])) {
                    $pages[$number] = $all[$number];
                }
            }
        }
        ksort($pages);

        return $pages;
    }

    /** @return list<float>|null */
    private function questionVector(string $question): ?array
    {
        try {
            $vectors = $this->provider->embed([$question]);
        } catch (Throwable) {
            return null; // keyword retrieval still works
        }

        return $vectors[0] ?? null;
    }

    /**
     * @param  list<float>  $a
     * @param  array<array-key, mixed>  $b
     */
    private function cosine(array $a, array $b): ?float
    {
        if (count($a) !== count($b) || $a === []) {
            return null;
        }

        $dot = $normA = $normB = 0.0;
        foreach ($a as $i => $value) {
            $other = (float) ($b[$i] ?? 0);
            $dot += $value * $other;
            $normA += $value * $value;
            $normB += $other * $other;
        }

        return $normA > 0 && $normB > 0 ? $dot / (sqrt($normA) * sqrt($normB)) : null;
    }
}
