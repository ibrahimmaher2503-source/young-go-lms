<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Domain\Media\ImageReencoder;
use App\Domain\Media\PdfInspector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Validates and stores one private transfer proof. The content decides what the file is (never the client name or type):
 * only png, jpeg, webp and PDF within the size limit pass; images are re-encoded (no hidden metadata), PDFs are inspected
 * (no encryption or active content) and the stored name is random. Failures are ValidationException on key `proof`.
 */
final class ManualPaymentProofFiles
{
    /** @return array{mime: string, size: int, sha256: string, path: string} */
    public static function store(File $file): array
    {
        $maxKilobytes = Config::integer('manual_payments.max_kilobytes');
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $maxKilobytes * 1024) {
            throw ValidationException::withMessages(['proof' => __('manual_payment.file_too_large', ['max' => round($maxKilobytes / 1024, 1)])]);
        }

        /** @var array<string, string> $allowed */
        $allowed = (array) Config::get('manual_payments.mimes', []);
        $mime = (string) $file->getMimeType();
        if (! isset($allowed[$mime])) {
            throw ValidationException::withMessages(['proof' => __('manual_payment.file_type')]);
        }

        $extension = $allowed[$mime];
        $bytes = null;
        if (str_starts_with($mime, 'image/')) {
            $image = ImageReencoder::reencode((string) file_get_contents($file->getPathname()));
            $bytes = $image['bytes'];
            $mime = $image['mime'];
            $extension = $image['extension'];
        } else {
            $report = PdfInspector::inspect($file->getPathname());
            if (! $report['valid'] || $report['encrypted'] || $report['active_content']) {
                throw ValidationException::withMessages(['proof' => __('manual_payment.file_invalid')]);
            }
        }

        $disk = Storage::disk(Config::string('media.disk'));
        $path = trim(Config::string('manual_payments.directory'), '/').'/'.Str::uuid()->toString().'.'.$extension;
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
            throw ValidationException::withMessages(['proof' => __('manual_payment.file_invalid')]);
        }

        return ['mime' => $mime, 'size' => $size, 'sha256' => $hash, 'path' => $path];
    }

    public static function delete(string $path): void
    {
        Storage::disk(Config::string('media.disk'))->delete($path);
    }
}
