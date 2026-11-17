<?php

declare(strict_types=1);

namespace App\Domain\Assignments;

use App\Domain\Media\ImageReencoder;
use App\Domain\Media\PdfInspector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Validates and stores one private assignment file. The content decides what it is (never the client name or type): it
 * must match an allowed kind and the size limit; images are re-encoded (no hidden metadata), PDFs are inspected, and
 * the stored name is random. Returns what the caller records; it never records anything itself.
 */
final class AssignmentFiles
{
    /**
     * @param  list<string>  $allowedKinds  subset of image, video, audio, document
     * @return array{kind: string, mime: string, size: int, sha256: string, path: string}
     */
    public static function store(File $file, array $allowedKinds, int $maxKilobytes, string $directory): array
    {
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $maxKilobytes * 1024) {
            throw new AssignmentRefusedException(AssignmentRefusedException::TOO_LARGE, __('assignment.refused.too_large', ['max' => self::megabytes($maxKilobytes)]));
        }

        $mime = (string) $file->getMimeType();
        $kind = self::kindOf($mime);
        if ($kind === null || ! in_array($kind, $allowedKinds, true)) {
            throw new AssignmentRefusedException(AssignmentRefusedException::TYPE_NOT_ALLOWED);
        }

        $disk = Storage::disk(Config::string('media.disk'));
        $extension = (string) Config::get('assignments.extensions.'.$mime, 'bin');
        $bytes = null;

        if ($kind === 'image') {
            try {
                $image = ImageReencoder::reencode((string) file_get_contents($file->getPathname()));
            } catch (ValidationException) {
                throw new AssignmentRefusedException(AssignmentRefusedException::INVALID_FILE);
            }
            $bytes = $image['bytes'];
            $mime = $image['mime'];
            $extension = $image['extension'];
        } elseif ($mime === 'application/pdf') {
            $report = PdfInspector::inspect($file->getPathname());
            if (! $report['valid'] || $report['encrypted'] || $report['active_content']) {
                throw new AssignmentRefusedException(AssignmentRefusedException::INVALID_FILE);
            }
        }

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.'.$extension;
        if ($bytes !== null) {
            $stored = $disk->put($path, $bytes);
            $hash = hash('sha256', $bytes);
            $size = strlen($bytes);
        } else {
            $stream = fopen($file->getPathname(), 'rb');
            $stored = $stream !== false && $disk->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $hash = (string) hash_file('sha256', $file->getPathname());
        }
        if (! $stored) {
            throw new AssignmentRefusedException(AssignmentRefusedException::INVALID_FILE);
        }

        return ['kind' => $kind, 'mime' => $mime, 'size' => $size, 'sha256' => $hash, 'path' => $path];
    }

    public static function kindOf(string $mime): ?string
    {
        foreach ((array) Config::get('assignments.kinds', []) as $kind => $mimes) {
            if (in_array($mime, (array) $mimes, true)) {
                return (string) $kind;
            }
        }

        return null;
    }

    /** A safe name to show in lists: no path, no control characters, at most 150 characters. */
    public static function displayName(string $original): string
    {
        $name = basename(str_replace('\\', '/', $original));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = trim($name);

        return $name === '' ? 'file' : mb_substr($name, -150);
    }

    public static function megabytes(int $kilobytes): string
    {
        return rtrim(rtrim(number_format($kilobytes / 1024, 1), '0'), '.');
    }
}
