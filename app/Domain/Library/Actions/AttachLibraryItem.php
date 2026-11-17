<?php

declare(strict_types=1);

namespace App\Domain\Library\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Library\LibraryAccess;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Actions\UploadCurriculumDocument;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonResource;
use App\Models\LibraryItem;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Uses a library item in a lesson by reference: the lesson row points at the library file, so one upload serves many
 * lessons and the item cannot be deleted while any of them uses it. A curriculum PDF is the exception: the AI pipeline
 * needs its own versioned copy per lesson, so that path uploads a copy and runs the normal reading and approval steps.
 */
final class AttachLibraryItem
{
    private const array MEDIA_PROVIDERS = ['video' => 'signed_storage', 'audio' => 'audio', 'document' => 'document', 'image' => 'image'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AttachLessonMedia $attach,
        private readonly UploadCurriculumDocument $upload,
    ) {}

    public function asMedia(User $actor, LibraryItem $item, Lesson $lesson, ?int $durationSeconds = null): MediaAsset
    {
        $this->authorize($actor, $item, $lesson);
        $provider = self::MEDIA_PROVIDERS[$item->kind] ?? null;
        if ($provider === null) {
            throw ValidationException::withMessages(['item' => __('library.cannot_use_as_media')]);
        }

        return DB::transaction(function () use ($actor, $item, $lesson, $provider, $durationSeconds): MediaAsset {
            $asset = ($this->attach)($actor, $lesson, ['provider' => $provider, 'path' => $item->path, 'duration_seconds' => $durationSeconds]);
            $asset->forceFill(['library_item_id' => $item->getKey()])->save();

            return $asset;
        });
    }

    /** @param array<string, mixed> $meta optional title (ar/en); defaults to the library title */
    public function asResource(User $actor, LibraryItem $item, Lesson $lesson, array $meta = []): LessonResource
    {
        $this->authorize($actor, $item, $lesson);
        if (! in_array($item->kind, ['document', 'file', 'image'], true)) {
            throw ValidationException::withMessages(['item' => __('library.cannot_use_as_material')]);
        }

        return DB::transaction(function () use ($actor, $item, $lesson, $meta): LessonResource {
            Lesson::query()->whereKey($lesson->getKey())->lockForUpdate()->first();
            $resource = LessonResource::query()->create([
                'lesson_id' => $lesson->getKey(), 'type' => $item->kind === 'image' ? LessonResource::TYPE_IMAGE : LessonResource::TYPE_FILE,
                'title' => ! empty($meta['title']) ? (array) $meta['title'] : $item->getTranslations('title'),
                'path' => $item->path, 'mime' => $item->mime, 'size' => $item->size, 'sha256' => $item->sha256,
                'downloadable' => true, 'visibility' => 'enrolled', 'is_preview' => false, 'uploaded_by' => $actor->getKey(),
                'library_item_id' => $item->getKey(),
                'sort' => (int) LessonResource::query()->where('lesson_id', $lesson->getKey())->max('sort') + 1,
            ]);

            if ($this->audit->record('lesson_resource.added', $lesson, (int) $actor->getKey(), null, ['type' => $resource->type, 'library_item_id' => $item->getKey()]) === null) {
                throw new RuntimeException('Unable to record resource audit.');
            }

            return $resource;
        });
    }

    /** Uploads a copy as the lesson's next curriculum PDF version; reading and approval run exactly as for any upload. */
    public function asCurriculumPdf(User $actor, LibraryItem $item, Lesson $lesson): LessonCurriculumDocument
    {
        $this->authorize($actor, $item, $lesson);
        if ($item->kind !== 'document' || $item->mime !== 'application/pdf') {
            throw ValidationException::withMessages(['item' => __('library.cannot_use_as_pdf')]);
        }

        $stream = Storage::disk(Config::string('media.disk'))->readStream($item->path);
        if ($stream === null) {
            throw ValidationException::withMessages(['item' => __('media.file_missing')]);
        }
        $temp = tempnam(sys_get_temp_dir(), 'lib');
        $target = fopen($temp, 'wb');
        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        try {
            return ($this->upload)($actor, $lesson, new File($temp));
        } finally {
            @unlink($temp);
        }
    }

    private function authorize(User $actor, LibraryItem $item, Lesson $lesson): void
    {
        if (! LibraryAccess::canUse($actor, $item)) {
            abort(404);
        }
        $lessonItem = $lesson->item()->with('course')->first();
        abort_if($lessonItem === null, 404);
        CurriculumAccess::authorize($actor, $lessonItem->course);
    }
}
