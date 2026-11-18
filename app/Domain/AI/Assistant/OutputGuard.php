<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

/**
 * Deterministic server check of a model answer (AI spec 8.2). Nothing the model says reaches a learner unless it passes:
 * strict JSON, length caps, cited pages that exist, a supporting quote found on a cited page, and no leakage.
 */
final class OutputGuard
{
    /** @param  string  $systemPrompt  the rendered prompt rules; used to detect copied rule text */
    public function inspect(string $content, AssistantContext $context, string $systemPrompt): GuardVerdict
    {
        $data = json_decode(trim($content), true);
        if (! is_array($data) || array_is_list($data) || ! isset($data['status']) || ! is_string($data['status'])) {
            return GuardVerdict::rejected(GuardFailure::Schema);
        }

        $outcome = AssistantOutcome::tryFrom($data['status']);
        $keys = array_keys($data);
        sort($keys);
        $answer = $data['answer'] ?? null;
        $pages = $data['source_pages'] ?? null;
        $quote = $data['support_quote'] ?? null;
        $language = $data['language'] ?? null;
        if ($keys !== ['answer', 'language', 'source_pages', 'status', 'support_quote']
            || $outcome === null || $outcome === AssistantOutcome::Fallback
            || ! is_string($answer) || ! is_string($quote)
            || ! in_array($language, ['ar', 'en'], true) || ! is_array($pages) || ! array_is_list($pages)
            || count(array_filter($pages, is_int(...))) !== count($pages)) {
            return GuardVerdict::rejected(GuardFailure::Schema);
        }

        if ($outcome !== AssistantOutcome::Answered) {
            return GuardVerdict::accepted($outcome);
        }
        if (trim($answer) === '' || trim($quote) === '' || $pages === []) {
            return GuardVerdict::rejected(GuardFailure::Schema);
        }

        $cited = [];
        foreach ($pages as $page) {
            if (! isset($context->promptPages[$page])) {
                return GuardVerdict::rejected(GuardFailure::Citation);
            }
            $cited[$page] = $page;
        }
        $cited = array_values($cited);

        $fitted = $this->fit(trim($answer), $language === 'en' ? (int) config('ai.max_words_en', 70) : (int) config('ai.max_words', 80));
        if ($fitted === null) {
            return GuardVerdict::rejected(GuardFailure::Length);
        }

        if ($this->leaks($fitted[0], $context, $systemPrompt)) {
            return GuardVerdict::rejected(GuardFailure::Leakage);
        }

        if (! $this->quoteVerified($quote, $cited, $context)) {
            return GuardVerdict::rejected(GuardFailure::Quote);
        }

        return GuardVerdict::accepted(AssistantOutcome::Answered, $fitted[0], $cited, $language, true, $fitted[1], trim($quote));
    }

    /**
     * Keeps whole sentences within the sentence and word caps. A slightly long answer is trimmed at the last full
     * sentence; a clearly long one (or one with no sentence that fits) is rejected so the caller retries.
     *
     * @return array{0: string, 1: bool}|null
     */
    private function fit(string $answer, int $maxWords): ?array
    {
        $sentences = preg_split('/(?<=[.!?؟۔])\s+/u', $answer, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = static fn (array $parts): int => count(preg_split('/\s+/u', trim(implode(' ', $parts)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $total = $count($sentences);
        $maxSentences = (int) config('ai.max_sentences', 4);

        if ($sentences === [] || $total > $maxWords * (float) config('ai.assistant.trim_tolerance', 1.25)) {
            return null;
        }
        if (count($sentences) <= $maxSentences && $total <= $maxWords) {
            return [$answer, false];
        }

        $kept = array_slice($sentences, 0, $maxSentences);
        while ($kept !== [] && $count($kept) > $maxWords) {
            array_pop($kept);
        }

        return $kept === [] ? null : [implode(' ', $kept), true];
    }

    private function leaks(string $answer, AssistantContext $context, string $systemPrompt): bool
    {
        if (preg_match('/lesson_source|\[\[\s*page\b|\bai_(?:prompt|messages|conversations)\b|\blesson[\s_#-]*(?:id)?[\s:=#-]*\d+/iu', $answer) === 1) {
            return true;
        }

        // A link must come from the lesson text itself.
        if (preg_match_all('~(?:https?://|www\.)[^\s)]+~iu', $answer, $urls) > 0) {
            $source = implode("\n", $context->promptPages);
            foreach ($urls[0] as $url) {
                if (! str_contains($source, $url)) {
                    return true;
                }
            }
        }

        $size = max(3, (int) config('ai.assistant.leak_ngram_words', 6));
        $rules = TextNormalizer::words($systemPrompt);
        $copied = TextNormalizer::words($answer);
        $grams = [];
        for ($i = 0; $i + $size <= count($rules); $i++) {
            $grams[implode(' ', array_slice($rules, $i, $size))] = true;
        }
        for ($i = 0; $i + $size <= count($copied); $i++) {
            if (isset($grams[implode(' ', array_slice($copied, $i, $size))])) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<int>  $pages */
    private function quoteVerified(string $quote, array $pages, AssistantContext $context): bool
    {
        $words = TextNormalizer::words($quote);
        if ($words === [] || count($words) > (int) config('ai.assistant.quote_max_words', 25) || mb_strlen(implode(' ', $words)) < (int) config('ai.assistant.quote_min_chars', 8)) {
            return false;
        }

        foreach ($pages as $page) {
            if ($this->matches($words, TextNormalizer::words($context->promptPages[$page]))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verbatim after normalisation, or a window of page words close enough to the quote (tolerates small extraction noise).
     *
     * @param  list<string>  $quote
     * @param  list<string>  $page
     */
    private function matches(array $quote, array $page): bool
    {
        $needle = implode(' ', $quote);
        if (str_contains(' '.implode(' ', $page).' ', ' '.$needle.' ')) {
            return true;
        }

        $size = count($quote);
        $wanted = array_flip($quote);
        $candidates = [];
        for ($i = 0; $i + $size <= count($page); $i++) {
            $overlap = count(array_intersect_key(array_flip(array_slice($page, $i, $size)), $wanted)) / count($wanted);
            if ($overlap >= 0.8) {
                $candidates[$i] = $overlap;
            }
        }
        arsort($candidates);

        foreach (array_slice(array_keys($candidates), 0, 5) as $start) {
            similar_text($needle, implode(' ', array_slice($page, $start, $size)), $percent);
            if ($percent >= (float) config('ai.assistant.quote_min_similarity', 88)) {
                return true;
            }
        }

        return false;
    }
}
