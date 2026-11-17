<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Structural checks on an uploaded PDF without a PDF library: header and end marker, encryption, active content and
 * page count (including pages stored inside compressed object streams). The extraction pipeline (T-P3-09) brings a real
 * parser; until then anything this cannot read with confidence is rejected, never trusted.
 */
final class PdfInspector
{
    /** @return array{valid: bool, encrypted: bool, active_content: bool, pages: int} */
    public static function inspect(string $path): array
    {
        $bytes = (string) file_get_contents($path);

        $valid = preg_match('/%PDF-\d\.\d/', substr($bytes, 0, 1024)) === 1 && str_contains(substr($bytes, -2048), '%%EOF');

        return [
            'valid' => $valid,
            'encrypted' => preg_match('#/Encrypt\b#', $bytes) === 1,
            'active_content' => preg_match('#/(?:JavaScript|JS|Launch|EmbeddedFile|RichMedia)\b#', $bytes) === 1,
            'pages' => $valid ? self::pages($bytes) : 0,
        ];
    }

    private static function pages(string $bytes): int
    {
        $direct = preg_match_all('#/Type\s*/Page(?![A-Za-z])#', $bytes);
        if ($direct !== false && $direct > 0) {
            return $direct;
        }

        // Modern writers keep page objects inside compressed object streams: inflate those and count there.
        $count = 0;
        $offset = 0;
        while (($start = strpos($bytes, 'stream', $offset)) !== false) {
            $dataStart = $start + 6;
            if (isset($bytes[$dataStart]) && $bytes[$dataStart] === "\r") {
                $dataStart++;
            }
            if (isset($bytes[$dataStart]) && $bytes[$dataStart] === "\n") {
                $dataStart++;
            }
            $end = strpos($bytes, 'endstream', $dataStart);
            if ($end === false) {
                break;
            }

            $dictionary = substr($bytes, max(0, $start - 300), min(300, $start));
            if (str_contains($dictionary, '/ObjStm')) {
                $raw = substr($bytes, $dataStart, $end - $dataStart);
                $inflated = @gzuncompress($raw) ?: @gzinflate(substr($raw, 2));
                if (is_string($inflated)) {
                    $count += (int) preg_match_all('#/Type\s*/Page(?![A-Za-z])#', $inflated);
                }
            }
            $offset = $end + 9;
        }

        return $count;
    }
}
