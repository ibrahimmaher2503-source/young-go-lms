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
     * @param  array{positive-int, positive-int}|null  $cropRatio
     * @return array{bytes: string, mime: string, extension: string}
     *
     * @throws ValidationException
     */
    public static function reencode(string $bytes, ?array $cropRatio = null): array
    {
        if ($cropRatio !== null) {
            $header = @getimagesizefromstring($bytes);
            if ($header === false || max($header[0], $header[1]) > 8192 || $header[0] * $header[1] > 12_000_000
                || $header[0] < $cropRatio[0] || $header[1] < $cropRatio[1]) {
                throw ValidationException::withMessages(['file' => __('media.invalid_type')]);
            }
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ValidationException::withMessages(['file' => __('media.invalid_type')]);
        }

        if ($cropRatio !== null) {
            $image = self::crop($image, ...$cropRatio);
        } else {
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

    private static function crop(\GdImage $source, int $ratioWidth, int $ratioHeight): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $units = min(intdiv($width, $ratioWidth), intdiv($height, $ratioHeight));
        $cropWidth = $units * $ratioWidth;
        $cropHeight = $units * $ratioHeight;
        $targetUnits = min($units, intdiv(1600, max($ratioWidth, $ratioHeight)));
        $targetWidth = $targetUnits * $ratioWidth;
        $targetHeight = $targetUnits * $ratioHeight;
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false) {
            throw new RuntimeException('Unable to create the cropped image.');
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        if (! imagecopyresampled(
            $target, $source, 0, 0, intdiv($width - $cropWidth, 2), intdiv($height - $cropHeight, 2),
            $targetWidth, $targetHeight, $cropWidth, $cropHeight,
        )) {
            throw new RuntimeException('Unable to crop the image.');
        }

        return $target;
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
