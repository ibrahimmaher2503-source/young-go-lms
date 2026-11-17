<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

use App\Models\AiAnswerCache;
use Illuminate\Database\Eloquent\Builder;

/**
 * Per-lesson answer cache (AI spec 10.3, T-P7-02). The key is lesson + document version + normalised question + reply language
 * + style tier, so a new document version never reads an old answer. Entries hold no learner data; the caller decides what is
 * cacheable (no personal data in the question, first turn of a thread, normal mode). Exact match only: no semantic matching.
 */
final class AnswerCache
{
    public function find(int $lessonId, int $version, string $question, string $language, StyleTier $tier, int $promptVersion): ?CachedAnswer
    {
        $row = $this->query($lessonId, $version, $question, $language, $tier)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
        $data = $row?->answer_json;
        if ($row === null || ! is_array($data) || ($data['prompt_version'] ?? null) !== $promptVersion || ! is_string($data['answer'] ?? null) || ! is_array($data['pages'] ?? null)) {
            return null;
        }

        $row->increment('hits');

        return new CachedAnswer($data['answer'], array_values(array_map(intval(...), $data['pages'])), is_string($data['language'] ?? null) ? $data['language'] : $language);
    }

    /** @param  list<int>  $pages */
    public function store(int $lessonId, int $version, string $question, string $language, StyleTier $tier, int $promptVersion, string $answer, array $pages, string $answerLanguage): void
    {
        // Old versions and expired rows of this lesson go on every write: a new document version invalidates the cache.
        AiAnswerCache::query()->where('lesson_id', $lessonId)
            ->where(fn ($query) => $query->where('document_version', '!=', $version)->orWhere('expires_at', '<=', now()))
            ->delete();

        AiAnswerCache::query()->updateOrCreate(
            ['lesson_id' => $lessonId, 'document_version' => $version, 'question_hash' => $this->hash($question), 'locale' => $language, 'style_tier' => $tier->value],
            [
                'answer_json' => ['answer' => $answer, 'pages' => $pages, 'language' => $answerLanguage, 'prompt_version' => $promptVersion],
                'hits' => 0,
                'expires_at' => now()->addDays(max(1, (int) config('ai.cache.ttl_days', 30))),
            ],
        );
    }

    /** @return Builder<AiAnswerCache> */
    private function query(int $lessonId, int $version, string $question, string $language, StyleTier $tier): Builder
    {
        return AiAnswerCache::query()->where([
            'lesson_id' => $lessonId, 'document_version' => $version, 'question_hash' => $this->hash($question),
            'locale' => $language, 'style_tier' => $tier->value,
        ]);
    }

    private function hash(string $question): string
    {
        return hash('sha256', TextNormalizer::normalize($question));
    }
}
