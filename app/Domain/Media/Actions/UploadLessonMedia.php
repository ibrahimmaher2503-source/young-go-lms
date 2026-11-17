<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Media\ImageReencoder;
use App\Domain\Media\MediaProviders;
use App\Domain\Media\Providers\PrivateFileProvider;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/**
 * Stores an uploaded file privately and attaches it to a lesson. The file's content decides its type (never the
 * client's name or declared type), the stored name is random, and images are re-encoded to drop metadata.
 */
final class UploadLessonMedia
{
    /** content type => extension we store under */
    private const array EXTENSIONS = [
        'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/x-m4v' => 'm4v',
        'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
        'application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp',
    ];

    public function __construct(private readonly AttachLessonMedia $attach) {}

    public function __invoke(User $actor, Lesson $lesson, string $provider, File $file, string $clientName = '', ?int $durationSeconds = null): MediaAsset
    {
        $item = $lesson->item()->with('course')->first();
        abort_if($item === null, 404);
        // Authorize before anything is written so a refused upload leaves no file behind.
        CurriculumAccess::authorize($actor, $item->course);

        $driver = MediaProviders::for($provider);
        if (! $driver instanceof PrivateFileProvider) {
            throw ValidationException::withMessages(['provider' => __('media.unknown_provider')]);
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > Config::integer('media.max_kilobytes') * 1024) {
            throw ValidationException::withMessages(['file' => __('media.too_large')]);
        }

        $mime = (string) $file->getMimeType();
        $extension = self::EXTENSIONS[$mime] ?? null;
        if ($extension === null || ! in_array($mime, $driver->mimes(), true) || ! in_array($extension, $driver->extensions(), true)) {
            throw ValidationException::withMessages(['file' => __('media.invalid_type')]);
        }

        $claimed = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if ($claimed !== '' && ! in_array($claimed, $driver->extensions(), true)) {
            throw ValidationException::withMessages(['file' => __('media.invalid_type')]);
        }

        $disk = Storage::disk(Config::string('media.disk'));
        $path = 'media/'.Str::uuid()->toString().'.'.$extension;

        if ($driver->kind() === 'image') {
            $image = ImageReencoder::reencode((string) file_get_contents($file->getPathname()));
            $path = 'media/'.Str::uuid()->toString().'.'.$image['extension'];
            $stored = $disk->put($path, $image['bytes']);
        } else {
            // Streamed, so a large video is never loaded into memory.
            $stream = fopen($file->getPathname(), 'rb');
            $stored = $stream !== false && $disk->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if (! $stored) {
            throw ValidationException::withMessages(['file' => __('media.store_failed')]);
        }

        try {
            return ($this->attach)($actor, $lesson, ['provider' => $provider, 'path' => $path, 'duration_seconds' => $durationSeconds]);
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
    }
}
