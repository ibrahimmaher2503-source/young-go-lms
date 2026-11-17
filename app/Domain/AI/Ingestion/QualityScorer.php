<?php

declare(strict_types=1);

namespace App\Domain\AI\Ingestion;

/** AI spec 6.4: one 0-100 score per page from how much of the text is real, readable language. */
final class QualityScorer
{
    public function score(string $text, float $ocrConfidence = 1.0): float
    {
        $total = mb_strlen((string) preg_replace('/\s+/u', '', $text));
        if ($total === 0) {
            return 0.0;
        }

        $readable = preg_match_all('/[\p{Arabic}\p{Latin}\p{N}\p{P}\p{S}]/u', (string) preg_replace('/\s+/u', '', $text));
        $presentation = (int) preg_match_all('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
        $letters = max(1, (int) preg_match_all('/\p{L}/u', $text));

        $score = 100 * (((int) $readable - $presentation) / $total) - 200 * ($presentation / $letters);

        $words = preg_split('/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words !== []) {
            $average = array_sum(array_map(mb_strlen(...), $words)) / count($words);
            if ($average < 2 || $average > 15) {
                $score -= 25;
            }
        }

        return round(max(0.0, min(100.0, $score)) * max(0.0, min(1.0, $ocrConfidence)), 2);
    }
}
