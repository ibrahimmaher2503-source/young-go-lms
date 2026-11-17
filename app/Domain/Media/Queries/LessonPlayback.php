<?php

declare(strict_types=1);

namespace App\Domain\Media\Queries;

use App\Domain\Media\MediaProviders;
use App\Domain\Media\PlaybackDescriptor;
use App\Domain\Media\SignedFileUrl;
use App\Models\Lesson;
use App\Models\User;

/**
 * Playback details for a lesson, produced by its provider. The caller must already have decided the learner may watch:
 * the course player checks entitlement (T-P4-01) before asking for this. Caption tracks are added only when a viewer is
 * given and the file access rules let that viewer open them, each as a short-lived link tied to that account.
 */
final class LessonPlayback
{
    public static function for(Lesson $lesson, ?User $viewer = null): ?PlaybackDescriptor
    {
        $media = $lesson->media;
        if ($media === null || $media->status !== 'ready') {
            return null;
        }

        $descriptor = MediaProviders::for($media->provider)->descriptor($media);
        if ($viewer === null) {
            return $descriptor;
        }

        $signer = app(SignedFileUrl::class);
        $tracks = [];
        foreach ($lesson->subtitleTracks as $track) {
            $url = $signer->for($viewer, $track);
            if ($url !== null) {
                $tracks[] = ['locale' => $track->locale, 'label' => $track->label, 'default' => $track->is_default, 'url' => $url];
            }
        }

        return $tracks === [] ? $descriptor : $descriptor->withTracks($tracks);
    }
}
