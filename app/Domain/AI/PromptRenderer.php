<?php

declare(strict_types=1);

namespace App\Domain\AI;

use App\Models\AiPromptVersion;

/** Fills the stored template. Lesson text is data: it can never close the source tag or add a fake page marker. */
final class PromptRenderer
{
    /** @param  array<int, string>  $pages  page number => text */
    public function render(AiPromptVersion $prompt, string $tier, string $replyLanguage, string $lessonTitle, int $documentVersion, array $pages, ?int $maxWords = null): string
    {
        $tag = (string) config('ai.source_tag', 'lesson_source');
        $tagged = '';
        foreach ($pages as $number => $text) {
            $tagged .= '[[page '.$number."]]\n".$this->neutralize($text, $tag)."\n";
        }

        return strtr($prompt->template, [
            '{max_words}' => (string) ($maxWords ?? config('ai.max_words', 80)),
            '{style_tier_instructions}' => $prompt->style_tiers[$tier] ?? '',
            '{reply_language}' => $replyLanguage,
            '{lesson_title}' => $this->neutralize($lessonTitle, $tag),
            '{document_version}' => (string) $documentVersion,
            '{page_tagged_text}' => $tagged,
        ]);
    }

    private function neutralize(string $text, string $tag): string
    {
        $text = (string) preg_replace('/<\s*\/?\s*'.preg_quote($tag, '/').'\s*>/iu', '', $text);

        return (string) preg_replace('/\[\[\s*page\s+\d+\s*\]\]/iu', '', $text);
    }
}
