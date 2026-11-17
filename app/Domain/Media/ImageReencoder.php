<?php

declare(strict_types=1);

namespace App\Domain\Media;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Re-encodes an uploaded image so camera metadata (EXIF, GPS) and anything hidden after the pixels never reach storage. */
final class ImageReencoder
{
    private const int MAX_EDGE = 4096;

    /**
     * @return array{bytes: string, mime: string, extension: string}
     *
     * @throws ValidationException
     */
    public static function reencode(string $bytes): array
    {
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ValidationException::withMessages(['file' => __('media.invalid_type')]);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        if ($scale < 1) {
            $resized = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            if ($resized === false) {
                throw new RuntimeException('Unable to resize the image.');
            }
            $image = $resized;
        }

        $hasAlpha = self::hasAlpha($bytes);
        ob_start();
        try {
            $ok = $hasAlpha ? imagepng($image, null, 6) : imagejpeg($image, null, 88);
        } finally {
            $output = (string) ob_get_clean();
        }
        if (! $ok || $output === '') {
            throw new RuntimeException('Unable to encode the image.');
        }

        return ['bytes' => $output, 'mime' => $hasAlpha ? 'image/png' : 'image/jpeg', 'extension' => $hasAlpha ? 'png' : 'jpg'];
    }

    /** PNG and WebP can carry transparency; everything else is flattened to JPEG. */
    private static function hasAlpha(string $bytes): bool
    {
        if (str_starts_with($bytes, "\x89PNG")) {
            // Colour type 4 (grey+alpha) and 6 (RGBA) sit at byte 25 of the IHDR chunk.
            return in_array(ord($bytes[25] ?? "\0"), [4, 6], true);
        }

        return str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP';
    }
}
