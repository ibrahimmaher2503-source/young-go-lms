<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Media\ImageReencoder;
use App\Domain\Media\PdfInspector;
use App\Domain\Media\SourceUrl;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/** Per-lesson materials: PDFs, text files, images (re-encoded) and links. Files are private with random names. */
final class ManageLessonResources
{
    /** content type => extension; the file's content decides, never the client's name */
    private const array FILES = ['application/pdf' => 'pdf', 'text/plain' => 'txt'];

    private const array IMAGES = ['image/png', 'image/jpeg', 'image/webp'];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $meta title (ar/en), downloadable, is_preview */
    public function addFile(User $actor, Lesson $lesson, File $file, array $meta = []): LessonResource
    {
        $this->authorize($actor, $lesson);
        $attributes = $this->meta($meta);

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > Config::integer('media.resource_max_kilobytes') * 1024) {
            throw ValidationException::withMessages(['file' => __('media.too_large')]);
        }

        $mime = (string) $file->getMimeType();
        $disk = Storage::disk(Config::string('media.disk'));

        if (in_array($mime, self::IMAGES, true)) {
            $image = ImageReencoder::reencode((string) file_get_contents($file->getPathname()));
            $path = 'resources/'.Str::uuid()->toString().'.'.$image['extension'];
            $stored = $disk->put($path, $image['bytes']);
            $type = LessonResource::TYPE_IMAGE;
            $mime = $image['mime'];
            $size = strlen($image['bytes']);
            $hash = hash('sha256', $image['bytes']);
        } elseif (isset(self::FILES[$mime])) {
            if ($mime === 'application/pdf') {
                $report = PdfInspector::inspect($file->getPathname());
                if (! $report['valid'] || $report['encrypted'] || $report['active_content']) {
                    throw ValidationException::withMessages(['file' => __('media.pdf_invalid')]);
                }
            }
            $path = 'resources/'.Str::uuid()->toString().'.'.self::FILES[$mime];
            $stream = fopen($file->getPathname(), 'rb');
            $stored = $stream !== false && $disk->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $type = LessonResource::TYPE_FILE;
            $hash = (string) hash_file('sha256', $file->getPathname());
        } else {
            throw ValidationException::withMessages(['file' => __('media.invalid_type')]);
        }

        if (! $stored) {
            throw ValidationException::withMessages(['file' => __('media.store_failed')]);
        }

        return $this->create($actor, $lesson, [...$attributes, 'type' => $type, 'path' => $path, 'mime' => $mime, 'size' => $size, 'sha256' => $hash], $path);
    }

    /** @param array<string, mixed> $meta title (ar/en), url, downloadable, is_preview */
    public function addLink(User $actor, Lesson $lesson, array $meta): LessonResource
    {
        $this->authorize($actor, $lesson);
        $url = SourceUrl::https($meta['url'] ?? null);
        $clean = 'https://'.$url['host'].$url['path'].($url['query'] === '' ? '' : '?'.$url['query']);

        return $this->create($actor, $lesson, [...$this->meta($meta), 'type' => LessonResource::TYPE_LINK, 'url' => $clean, 'downloadable' => false], null);
    }

    public function remove(User $actor, LessonResource $resource): void
    {
        $this->authorize($actor, $resource->lesson, CurriculumAccess::DELETE);

        DB::transaction(function () use ($actor, $resource): void {
            $resource->delete();
            if ($this->audit->record('lesson_resource.removed', $resource->lesson, (int) $actor->getKey(), ['type' => $resource->type]) === null) {
                throw new RuntimeException('Unable to record resource audit.');
            }
        });

        // A file that belongs to the library is never deleted with the lesson's reference to it.
        if ($resource->path !== null && $resource->library_item_id === null) {
            Storage::disk(Config::string('media.disk'))->delete($resource->path);
        }
    }

    private function authorize(User $actor, Lesson $lesson, string $permission = CurriculumAccess::UPDATE): void
    {
        $item = $lesson->item()->with('course')->first();
        abort_if($item === null, 404);
        CurriculumAccess::authorize($actor, $item->course, $permission);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(User $actor, Lesson $lesson, array $attributes, ?string $path): LessonResource
    {
        try {
            return DB::transaction(function () use ($actor, $lesson, $attributes): LessonResource {
                Lesson::query()->whereKey($lesson->getKey())->lockForUpdate()->first();
                $resource = LessonResource::query()->create([
                    ...$attributes, 'lesson_id' => $lesson->getKey(), 'uploaded_by' => $actor->getKey(),
                    'sort' => (int) LessonResource::query()->where('lesson_id', $lesson->getKey())->max('sort') + 1,
                ]);

                if ($this->audit->record('lesson_resource.added', $lesson, (int) $actor->getKey(), null, ['type' => $resource->type, 'downloadable' => $resource->downloadable]) === null) {
                    throw new RuntimeException('Unable to record resource audit.');
                }

                return $resource;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk(Config::string('media.disk'))->delete($path);
            }
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function meta(array $meta): array
    {
        $validated = Validator::make($meta, [
            'title' => ['required', 'array'], 'title.ar' => ['nullable', 'string', 'max:150'], 'title.en' => ['nullable', 'string', 'max:150'],
            'downloadable' => ['sometimes', 'boolean'], 'is_preview' => ['sometimes', 'boolean'],
        ])->validate();

        $title = array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : '', (array) $validated['title']), static fn (string $v): bool => $v !== '');
        if ($title === []) {
            throw ValidationException::withMessages(['title.ar' => __('validation.required', ['attribute' => __('curriculum.title')])]);
        }

        $preview = (bool) ($validated['is_preview'] ?? false);

        return [
            'title' => $title, 'downloadable' => (bool) ($validated['downloadable'] ?? true),
            'is_preview' => $preview, 'visibility' => $preview ? 'public_preview' : 'enrolled',
        ];
    }
}
