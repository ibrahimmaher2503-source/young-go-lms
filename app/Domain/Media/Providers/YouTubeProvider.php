<?php

declare(strict_types=1);

namespace App\Domain\Media\Providers;

use App\Domain\Media\Contracts\VideoDeliveryProvider;
use App\Domain\Media\PlaybackDescriptor;
use App\Domain\Media\SourceUrl;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

/** T3 open: an unlisted YouTube video. Anyone with the link can watch, so paid lessons need an admin exception. */
final class YouTubeProvider implements VideoDeliveryProvider
{
    public function key(): string
    {
        return 'youtube';
    }

    public function kind(): string
    {
        return 'video';
    }

    public function tier(): string
    {
        return MediaAsset::TIER_OPEN;
    }

    public function validate(array $source): array
    {
        $url = SourceUrl::https($source['url'] ?? null);
        $id = null;

        if ($url['host'] === 'youtu.be' && preg_match('#^/([A-Za-z0-9_-]{11})/?$#', $url['path'], $match) === 1) {
            $id = $match[1];
        } elseif (in_array($url['host'], ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            if ($url['path'] === '/watch' && preg_match('/(?:^|&)v=([A-Za-z0-9_-]{11})(?:&|$)/', $url['query'], $match) === 1) {
                $id = $match[1];
            } elseif (preg_match('#^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$#', $url['path'], $match) === 1) {
                $id = $match[1];
            }
        }

        if ($id === null) {
            throw ValidationException::withMessages(['url' => __('media.invalid_youtube')]);
        }

        return ['provider' => 'youtube', 'kind' => 'video', 'external_id' => $id, 'url' => null, 'protection_tier' => $this->tier(), 'status' => 'ready', 'metadata' => []];
    }

    public function descriptor(MediaAsset $asset): PlaybackDescriptor
    {
        return new PlaybackDescriptor(
            PlaybackDescriptor::EMBED, 'youtube', MediaAsset::TIER_OPEN,
            embedUrl: 'https://www.youtube-nocookie.com/embed/'.$asset->external_id.'?rel=0', sandbox: Config::string('media.iframe_sandbox'),
        );
    }
}
