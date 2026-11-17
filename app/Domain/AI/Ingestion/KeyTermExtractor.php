<?php

declare(strict_types=1);

namespace App\Domain\AI\Ingestion;

/** AI spec 6.6: 3 to 8 terms for starter chips, only words that really appear in the approved text. No generation. */
final class KeyTermExtractor
{
    private const array STOP = [
        'this', 'that', 'with', 'from', 'have', 'will', 'your', 'they', 'them', 'then', 'than', 'also', 'into', 'what', 'when', 'which',
        'هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'التى', 'على', 'إلى', 'الى', 'عن', 'مع', 'كان', 'كانت', 'يكون', 'أن', 'إن', 'ثم', 'كما', 'قد', 'لكن', 'عند', 'بعد', 'قبل', 'حتى', 'منذ',
    ];

    /** @return list<string> */
    public function extract(string $text): array
    {
        $max = (int) config('ai.ingestion.key_terms_max', 8);
        $min = (int) config('ai.ingestion.key_terms_min', 3);

        $counts = [];
        $first = [];
        $words = preg_split('/[\s\p{P}\p{S}\d]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $position => $word) {
            if (mb_strlen($word) < 4 || in_array($word, self::STOP, true)) {
                continue;
            }
            $counts[$word] = ($counts[$word] ?? 0) + 1;
            $first[$word] ??= $position;
        }

        uksort($counts, static fn (string $a, string $b): int => [$counts[$b], $first[$a]] <=> [$counts[$a], $first[$b]]);
        $terms = array_slice(array_keys($counts), 0, $max);

        return count($terms) >= $min ? $terms : [];
    }

    public function localeOf(string $text): string
    {
        $arabic = (int) preg_match_all('/\p{Arabic}/u', $text);
        $latin = (int) preg_match_all('/\p{Latin}/u', $text);

        return $arabic >= $latin ? 'ar' : 'en';
    }
}
