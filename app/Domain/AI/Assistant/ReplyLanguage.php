<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

/** Reply in the language of the question, defaulting to the UI locale (AI spec 4). */
final readonly class ReplyLanguage
{
    private function __construct(public string $code, public string $label) {}

    public static function for(string $question, string $locale): self
    {
        $arabic = preg_match_all('/\p{Arabic}/u', $question);
        $latin = preg_match_all('/\p{Latin}/u', $question);

        if ($arabic > $latin) {
            return new self('ar', 'Arabic');
        }

        if ($latin > 0 && $locale === 'ar') {
            // English, or Franco-Arabic typed in Latin letters: the prompt rules answer the second one in simple Arabic.
            return new self('ar', 'the language of the question (simple Arabic if it is Franco-Arabic)');
        }

        return $latin > 0 || $locale !== 'ar' ? new self('en', 'English') : new self('ar', 'Arabic');
    }

    public function maxWords(): int
    {
        return $this->code === 'en' ? (int) config('ai.max_words_en', 70) : (int) config('ai.max_words', 80);
    }
}
