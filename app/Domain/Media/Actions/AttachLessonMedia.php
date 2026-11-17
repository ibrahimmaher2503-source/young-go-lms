<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\CurriculumAccess;
use App\Domain\Media\MediaProviders;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Sets a lesson's media from a validated source. A lesson has one media source; attaching again replaces it. */
final class AttachLessonMedia
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $source provider plus that provider's fields (url, path or text) */
    public function __invoke(User $actor, Lesson $lesson, array $source): MediaAsset
    {
        $item = $lesson->item()->with('course')->first();
        abort_if($item === null, 404);
        CurriculumAccess::authorize($actor, $item->course);

        $attributes = MediaProviders::for($source['provider'] ?? null)->validate($source);
        if (isset($source['duration_seconds']) && is_numeric($source['duration_seconds'])) {
            $attributes['duration_seconds'] = max(0, min(86400, (int) $source['duration_seconds']));
        }

        return DB::transaction(function () use ($actor, $lesson, $attributes): MediaAsset {
            // Replacing the source also drops any exception granted for the previous one.
            MediaAsset::query()->where('lesson_id', $lesson->getKey())->delete();
            $asset = MediaAsset::query()->create([...$attributes, 'lesson_id' => $lesson->getKey()]);

            if ($this->audit->record('media.attached', $lesson, (int) $actor->getKey(), null, ['provider' => $asset->provider, 'tier' => $asset->protection_tier]) === null) {
                throw new RuntimeException('Unable to record media audit.');
            }

            return $asset;
        });
    }
}
