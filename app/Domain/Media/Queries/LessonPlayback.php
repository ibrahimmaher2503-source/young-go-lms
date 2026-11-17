<?php

declare(strict_types=1);

namespace App\Domain\Media\Queries;

use App\Domain\Media\MediaProviders;
use App\Domain\Media\PlaybackDescriptor;
use App\Models\Lesson;

/**
 * Playback details for a lesson, produced by its provider. The caller must already have decided the learner may watch:
 * the course player checks entitlement (T-P4-01) before asking for this.
 */
final class LessonPlayback
{
    public static function for(Lesson $lesson): ?PlaybackDescriptor
    {
        $media = $lesson->media;
        if ($media === null || $media->status !== 'ready') {
            return null;
        }

        return MediaProviders::for($media->provider)->descriptor($media);
    }
}
