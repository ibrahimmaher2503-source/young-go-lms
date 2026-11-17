<?php

declare(strict_types=1);

namespace App\Domain\Library\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Library\LibraryAccess;
use App\Domain\Media\ImageReencoder;
use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/**
 * Stores a file in the library. The file's content decides its kind (never the client's name), the stored name is random,
 * images are re-encoded to drop metadata, and only the owner scope the actor may use is accepted.
 */
final class AddLibraryItem
{
    /** content type => [kind, extension] */
    private const array TYPES = [
        'video/mp4' => ['video', 'mp4'], 'video/webm' => ['video', 'webm'], 'video/quicktime' => ['video', 'mov'], 'video/x-m4v' => ['video', 'm4v'],
        'audio/mpeg' => ['audio', 'mp3'], 'audio/mp4' => ['audio', 'm4a'], 'audio/x-m4a' => ['audio', 'm4a'], 'audio/ogg' => ['audio', 'ogg'], 'audio/wav' => ['audio', 'wav'], 'audio/x-wav' => ['audio', 'wav'],
        'application/pdf' => ['document', 'pdf'], 'text/plain' => ['file', 'txt'],
        'image/png' => ['image', 'png'], 'image/jpeg' => ['image', 'jpg'], 'image/webp' => ['image', 'webp'],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $meta title (ar/en), tags (list), scope: academy|mine */
    public function __invoke(User $actor, File $file, array $meta): LibraryItem
    {
        if (! LibraryAccess::mayUseLibrary($actor)) {
            throw new AuthorizationException;
        }

        $scope = ($meta['scope'] ?? 'mine') === 'academy' ? LibraryItem::OWNER_ACADEMY : LibraryItem::OWNER_INSTRUCTOR;
        if ($scope === LibraryItem::OWNER_ACADEMY && ! LibraryAccess::managesAll($actor)) {
            throw new AuthorizationException; // sharing with the whole academy needs the media permission
        }

        $validated = Validator::make($meta, [
            'title' => ['required', 'array'], 'title.ar' => ['nullable', 'string', 'max:150'], 'title.en' => ['nullable', 'string', 'max:150'],
            'tags' => ['nullable', 'array', 'max:10'], 'tags.*' => ['string', 'max:40'],
        ])->validate();
        $title = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : '', (array) $validated['title']), static fn (string $v): bool => $v !== '');
        if ($title === []) {
            throw ValidationException::withMessages(['title.ar' => __('validation.required', ['attribute' => __('curriculum.title')])]);
        }
        $tags = array_values(array_unique(array_filter(array_map(static fn ($t) => Str::lower(trim((string) $t)), (array) ($validated['tags'] ?? [])))));

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > Config::integer('media.max_kilobytes') * 1024) {
            throw ValidationException::withMessages(['file' => __('media.too_large')]);
        }
        $mime = (string) $file->getMimeType();
        [$kind, $extension] = self::TYPES[$mime] ?? [null, null];
        if ($kind === null) {
            throw ValidationException::withMessages(['file' => __('media.invalid_type')]);
        }

        $disk = Storage::disk(Config::string('media.disk'));
        if ($kind === 'image') {
            $image = ImageReencoder::reencode((string) file_get_contents($file->getPathname()));
            $path = 'library/'.Str::uuid()->toString().'.'.$image['extension'];
            $stored = $disk->put($path, $image['bytes']);
            $mime = $image['mime'];
            $size = strlen($image['bytes']);
            $hash = hash('sha256', $image['bytes']);
        } else {
            $path = 'library/'.Str::uuid()->toString().'.'.$extension;
            $stream = fopen($file->getPathname(), 'rb');
            $stored = $stream !== false && $disk->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $hash = (string) hash_file('sha256', $file->getPathname());
        }
        if (! $stored) {
            throw ValidationException::withMessages(['file' => __('media.store_failed')]);
        }

        try {
            return DB::transaction(function () use ($actor, $scope, $kind, $title, $tags, $path, $mime, $size, $hash): LibraryItem {
                $item = LibraryItem::query()->create([
                    'owner_type' => $scope, 'owner_id' => $scope === LibraryItem::OWNER_INSTRUCTOR ? $actor->getKey() : null, 'kind' => $kind,
                    'title' => $title, 'tags' => $tags, 'path' => $path, 'mime' => $mime, 'size' => $size, 'sha256' => $hash, 'created_by' => $actor->getKey(),
                ]);
                if ($this->audit->record('library.item_added', $item, (int) $actor->getKey(), null, ['kind' => $kind, 'scope' => $scope]) === null) {
                    throw new RuntimeException('Unable to record library audit.');
                }

                return $item;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
    }
}
