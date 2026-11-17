<?php

declare(strict_types=1);

namespace App\Domain\Media;

/** Checks that a caption file is real WebVTT: signature, valid cue timings in order, no markup that could run. */
final class VttValidator
{
    public const int MAX_BYTES = 1048576;

    private const string TIME = '(?:\d{2,}:)?[0-5]\d:[0-5]\d\.\d{3}';

    /** @return string|null the reason key under media.vtt.* when invalid, null when the file is good */
    public static function problem(string $contents): ?string
    {
        if ($contents === '' || strlen($contents) > self::MAX_BYTES) {
            return 'size';
        }
        if (! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            return 'encoding';
        }

        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        if (! preg_match('/^WEBVTT(?:[ \t].*)?$/', (string) ($lines[0] ?? ''))) {
            return 'signature';
        }
        if (preg_match('/<\s*(script|iframe|object|embed)\b|javascript:/i', $contents)) {
            return 'markup';
        }

        $cues = 0;
        $previousStart = -1.0;
        foreach ($lines as $line) {
            if (! preg_match('/^('.self::TIME.')\s+-->\s+('.self::TIME.')(?:\s.*)?$/', $line, $match)) {
                continue;
            }
            [$start, $end] = [self::seconds($match[1]), self::seconds($match[2])];
            if ($end <= $start || $start < $previousStart) {
                return 'timing';
            }
            $previousStart = $start;
            $cues++;
        }

        return $cues === 0 ? 'no_cues' : null;
    }

    private static function seconds(string $time): float
    {
        $parts = array_map('floatval', explode(':', $time));

        return count($parts) === 3 ? $parts[0] * 3600 + $parts[1] * 60 + $parts[2] : $parts[0] * 60 + $parts[1];
    }
}
