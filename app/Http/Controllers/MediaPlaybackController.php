<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a protected (T1) file for one signed, expiring link. The course player asks for that link only after it has
 * checked the learner's access; the link itself expires within minutes (config media.signed_ttl).
 */
final class MediaPlaybackController
{
    public function __invoke(int $asset): StreamedResponse
    {
        $media = MediaAsset::query()->where('status', 'ready')->where('protection_tier', MediaAsset::TIER_PROTECTED)->whereNotNull('path')->find($asset);
        abort_if($media === null, 404);

        $disk = Storage::disk(Config::string('media.disk'));
        abort_unless($disk->exists((string) $media->path), 404);

        return $disk->response((string) $media->path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
