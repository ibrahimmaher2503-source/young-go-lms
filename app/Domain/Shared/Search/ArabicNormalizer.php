<?php

declare(strict_types=1);

namespace App\Domain\Shared\Search;

use InvalidArgumentException;
use Normalizer;

final class ArabicNormalizer
{
    public static function normalize(string $text): string
    {
        $normalized = Normalizer::normalize($text, Normalizer::FORM_KC);
        if ($normalized === false) {
            throw new InvalidArgumentException('Search text must use valid UTF-8.');
        }

        $normalized = strtr($normalized, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
            'ـ' => '',
        ]);
        $normalized = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return mb_strtolower(trim($normalized), 'UTF-8');
    }
}
