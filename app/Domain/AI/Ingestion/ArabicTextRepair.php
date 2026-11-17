<?php

declare(strict_types=1);

namespace App\Domain\AI\Ingestion;

use Normalizer;

/**
 * Cleans text pulled out of a PDF (AI spec 6.2): presentation forms back to base letters (NFKC), invisible and control
 * characters out, lines stored in visual order turned back, and headers or footers repeated across pages removed.
 */
final class ArabicTextRepair
{
    public function repair(string $text): string
    {
        $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Zero-width and bidi marks, then every other control character except newline and tab.
        $text = (string) preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{FFFD}]/u', '', $text);
        $text = (string) preg_replace('/[^\P{Cc}\n\t]/u', '', $text);

        $lines = array_map(fn (string $line): string => $this->fixVisualOrder(trim((string) preg_replace('/[ \t]+/u', ' ', $line))), explode("\n", $text));

        return trim((string) preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines)));
    }

    /**
     * @param  list<string>  $pages  already repaired page texts
     * @return list<string>
     */
    public function stripRepeatedLines(array $pages): array
    {
        $total = count($pages);
        if ($total < 3) {
            return $pages;
        }

        $seen = [];
        foreach ($pages as $page) {
            foreach (array_unique(array_map($this->signature(...), $this->lines($page))) as $signature) {
                if ($signature !== '') {
                    $seen[$signature] = ($seen[$signature] ?? 0) + 1;
                }
            }
        }

        $ratio = (float) config('ai.ingestion.repeat_ratio', 0.6);
        $repeated = array_keys(array_filter($seen, static fn (int $count): bool => $count / $total >= $ratio));

        return array_map(fn (string $page): string => trim(implode("\n", array_filter(
            $this->lines($page),
            fn (string $line): bool => ! in_array($this->signature($line), $repeated, true),
        ))), $pages);
    }

    /** @return list<string> */
    private function lines(string $page): array
    {
        return explode("\n", $page);
    }

    /**
     * Digits are masked so "Page 3" and "Page 4" count as the same footer, but only on short lines: a body sentence
     * that differs by a number is content, not a footer.
     */
    private function signature(string $line): string
    {
        $line = trim($line);
        $words = preg_split('/[\s\d\p{P}]+/u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($words) <= 3 ? trim((string) preg_replace('/\d+/u', '#', $line)) : $line;
    }

    /**
     * A line stored in visual order reads backwards. Arabic gives a reliable tell: the article "ال" starts words, so a
     * line where "لا" ends more words than "ال" starts them was reversed. Two or more such words are needed.
     */
    private function fixVisualOrder(string $line): string
    {
        $words = preg_split('/\s+/u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $starts = count(array_filter($words, static fn (string $w): bool => preg_match('/^ال./u', $w) === 1));
        $ends = count(array_filter($words, static fn (string $w): bool => preg_match('/.لا$/u', $w) === 1 && preg_match('/^ال/u', $w) !== 1));

        if ($ends < 2 || $ends <= $starts) {
            return $line;
        }

        $reversed = implode('', array_reverse(preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: []));

        // Numbers must keep their own digit order.
        return (string) preg_replace_callback('/\d+/u', static fn (array $m): string => strrev($m[0]), $reversed);
    }
}
