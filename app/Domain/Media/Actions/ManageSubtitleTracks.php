<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Media\VttValidator;
use App\Models\Lesson;
use App\Models\SubtitleTrack;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/** AR/EN captions for recorded lessons (ED-008: uploaded by people, never generated). One track per locale. */
final class ManageSubtitleTracks
{
    public const array LOCALES = ['ar', 'en'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function add(User $actor, Lesson $lesson, string $locale, string $label, File $file, bool $default = false): SubtitleTrack
    {
        $item = $lesson->item()->with('course')->first();
        abort_if($item === null, 404);
        CurriculumAccess::authorize($actor, $item->course);

        if (! in_array($lesson->type, ['video', 'audio'], true)) {
            throw ValidationException::withMessages(['file' => __('media.vtt.lesson_type')]);
        }
        if (! in_array($locale, self::LOCALES, true)) {
            throw ValidationException::withMessages(['locale' => __('media.vtt.locale')]);
        }
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 80) {
            throw ValidationException::withMessages(['label' => __('media.vtt.label')]);
        }

        $contents = (string) file_get_contents($file->getPathname());
        $problem = VttValidator::problem($contents);
        if ($problem !== null) {
            throw ValidationException::withMessages(['file' => __('media.vtt.'.$problem)]);
        }

        $disk = Storage::disk(Config::string('media.disk'));
        $path = 'subtitles/'.Str::uuid()->toString().'.vtt';
        if (! $disk->put($path, $contents)) {
            throw ValidationException::withMessages(['file' => __('media.store_failed')]);
        }

        try {
            return DB::transaction(function () use ($actor, $lesson, $locale, $label, $path, $contents, $default, $disk): SubtitleTrack {
                $replaced = SubtitleTrack::query()->where('lesson_id', $lesson->getKey())->where('locale', $locale)->first();
                $oldPath = $replaced?->path;
                $first = ! SubtitleTrack::query()->where('lesson_id', $lesson->getKey())->exists();
                $track = SubtitleTrack::query()->updateOrCreate(
                    ['lesson_id' => $lesson->getKey(), 'locale' => $locale],
                    ['label' => $label, 'path' => $path, 'sha256' => hash('sha256', $contents), 'size' => strlen($contents), 'uploaded_by' => $actor->getKey()],
                );
                if ($default || $first) {
                    $this->makeDefault($track);
                }
                if ($this->audit->record('media.subtitle_added', $lesson, (int) $actor->getKey(), null, ['locale' => $locale, 'replaced' => $replaced !== null]) === null) {
                    throw new RuntimeException('Unable to record subtitle audit.');
                }
                if ($oldPath !== null) {
                    DB::afterCommit(fn () => $disk->delete($oldPath));
                }

                return $track->refresh();
            });
        } catch (Throwable $exception) {
            if (! SubtitleTrack::query()->where('path', $path)->exists()) {
                $disk->delete($path);
            }
            throw $exception;
        }
    }

    public function setDefault(User $actor, SubtitleTrack $track): void
    {
        $this->authorize($actor, $track);
        DB::transaction(fn () => $this->makeDefault($track));
    }

    public function remove(User $actor, SubtitleTrack $track): void
    {
        $this->authorize($actor, $track);

        DB::transaction(function () use ($actor, $track): void {
            $lesson = $track->lesson;
            $path = $track->path;
            $locale = $track->locale;
            $wasDefault = $track->is_default;
            $track->delete();
            if ($wasDefault) {
                SubtitleTrack::query()->where('lesson_id', $lesson->getKey())->orderBy('id')->first()?->forceFill(['is_default' => true])->save();
            }
            if ($this->audit->record('media.subtitle_removed', $lesson, (int) $actor->getKey(), null, ['locale' => $locale]) === null) {
                throw new RuntimeException('Unable to record subtitle audit.');
            }
            DB::afterCommit(fn () => Storage::disk(Config::string('media.disk'))->delete($path));
        });
    }

    private function authorize(User $actor, SubtitleTrack $track): void
    {
        $item = $track->lesson->item()->with('course')->first();
        abort_if($item === null, 404);
        CurriculumAccess::authorize($actor, $item->course);
    }

    private function makeDefault(SubtitleTrack $track): void
    {
        SubtitleTrack::query()->where('lesson_id', $track->lesson_id)->whereKeyNot($track->getKey())->update(['is_default' => false]);
        $track->forceFill(['is_default' => true])->save();
    }
}
