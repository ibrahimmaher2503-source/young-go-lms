<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\ActingLearner;
use App\Domain\Media\PrivateFileAccess;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rechecks account-bound protected playback, including a parent's current child context, on every redemption.
 */
final class MediaPlaybackController
{
    public function __invoke(Request $request, int $asset, PrivateFileAccess $access, ActingLearner $acting): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        if ($request->query->has('actor')) {
            abort_unless((int) $request->query('actor') === (int) $user->getKey() && $user->status === 'active', 403);
            $user = $acting->current();
            abort_unless($user instanceof User && $acting->isParentContext(), 403);
        }
        abort_unless((int) $request->query('u') === (int) $user->getKey(), 403);

        $media = MediaAsset::query()->where('status', 'ready')->where('protection_tier', MediaAsset::TIER_PROTECTED)->whereNotNull('path')->find($asset);
        abort_if($media === null, 404);
        abort_unless($access->allows($user, $media), 403);

        $disk = Storage::disk(Config::string('media.disk'));
        abort_unless($disk->exists((string) $media->path), 404);

        return $disk->response((string) $media->path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
