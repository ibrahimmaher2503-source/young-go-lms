<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Media\ImageReencoder;
use App\Domain\Media\MediaProviders;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Course cover image and promo video reference (PRD 9.2 step 4). The cover is public marketing content: it is re-encoded
 * (no EXIF) and cropped to 16:9, then stored on the public disk. The promo video is only a validated reference through
 * the existing media providers; nothing is fetched or hosted.
 */
final class CourseMedia
{
    public const array PROMO_PROVIDERS = ['youtube', 'vimeo', 'external_url'];

    public const int MAX_KILOBYTES = 4096;

    private const int MIN_WIDTH = 640;

    /** Re-encodes, crops to 16:9 and stores an uploaded cover; returns the public-disk path. */
    public static function storeCover(UploadedFile $file): string
    {
        $fail = static fn (string $key): ValidationException => ValidationException::withMessages(['cover_image' => __($key)]);

        if ($file->getSize() === false || $file->getSize() < 1 || $file->getSize() > self::MAX_KILOBYTES * 1024) {
            throw $fail('media.too_large');
        }
        $bytes = (string) file_get_contents($file->getPathname());
        if (! in_array((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw $fail('media.invalid_type');
        }

        try {
            $image = ImageReencoder::reencode($bytes);
        } catch (ValidationException) {
            throw $fail('media.invalid_type');
        }

        $cropped = self::cropToWideScreen($image['bytes'], $image['mime']);
        if ($cropped === null) {
            throw ValidationException::withMessages(['cover_image' => __('course_wizard.cover_too_small', ['width' => self::MIN_WIDTH])]);
        }

        $path = 'courses/'.Str::uuid().'.'.$image['extension'];
        if (! Storage::disk('public')->put($path, $cropped, 'public')) {
            throw $fail('media.store_failed');
        }

        return $path;
    }

    public static function deleteCover(?string $path): void
    {
        if (is_string($path) && str_starts_with($path, 'courses/') && ! str_contains($path, '..')) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  array<string, mixed>  $input  provider and url as entered
     * @return array{provider: string, external_id: string|null, url: string|null, source_url: string, metadata: array<string, mixed>}|null
     */
    public static function promoVideo(array $input): ?array
    {
        $provider = $input['provider'] ?? null;
        $url = $input['url'] ?? null;
        if (blank($provider) && blank($url)) {
            return null;
        }
        if (! is_string($provider) || ! in_array($provider, self::PROMO_PROVIDERS, true)) {
            throw ValidationException::withMessages(['promo_video.provider' => __('media.unknown_provider')]);
        }

        try {
            $attributes = MediaProviders::for($provider)->validate(['url' => $url]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['promo_video.url' => (array) ($exception->errors()['url'] ?? __('media.invalid_url'))]);
        }

        return [
            'provider' => $provider,
            'external_id' => $attributes['external_id'] ?? null,
            'url' => $attributes['url'] ?? null,
            'source_url' => (string) $url,
            'metadata' => (array) ($attributes['metadata'] ?? []),
        ];
    }

    /** Centre crop to 16:9; null when the image is narrower than the minimum. */
    private static function cropToWideScreen(string $bytes, string $mime): ?string
    {
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < self::MIN_WIDTH || $height < (int) round(self::MIN_WIDTH * 9 / 16)) {
            return null;
        }

        $targetHeight = (int) round($width * 9 / 16);
        $rect = $targetHeight <= $height
            ? ['x' => 0, 'y' => intdiv($height - $targetHeight, 2), 'width' => $width, 'height' => $targetHeight]
            : ['x' => intdiv($width - (int) round($height * 16 / 9), 2), 'y' => 0, 'width' => (int) round($height * 16 / 9), 'height' => $height];
        $crop = imagecrop($image, $rect);
        if ($crop === false) {
            throw new RuntimeException('Unable to crop the cover image.');
        }

        ob_start();
        try {
            $mime === 'image/png' ? imagesavealpha($crop, true) && imagepng($crop, null, 6) : imagejpeg($crop, null, 88);
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output === '' ? null : $output;
    }
}
