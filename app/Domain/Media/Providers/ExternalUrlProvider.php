<?php

declare(strict_types=1);

namespace App\Domain\Media\Providers;

use App\Domain\Media\Contracts\VideoDeliveryProvider;
use App\Domain\Media\PlaybackDescriptor;
use App\Domain\Media\SourceUrl;
use App\Models\MediaAsset;
use Illuminate\Validation\ValidationException;

/** T3 open: a direct https link to an approved video file (mp4, webm or an HLS playlist). */
final class ExternalUrlProvider implements VideoDeliveryProvider
{
    private const array EXTENSIONS = ['mp4', 'webm', 'm3u8'];

    public function key(): string
    {
        return 'external_url';
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
        if (! in_array(strtolower(pathinfo($url['path'], PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
            throw ValidationException::withMessages(['url' => __('media.invalid_type')]);
        }

        $clean = 'https://'.$url['host'].$url['path'].($url['query'] === '' ? '' : '?'.$url['query']);

        return ['provider' => 'external_url', 'kind' => 'video', 'external_id' => null, 'url' => $clean, 'protection_tier' => $this->tier(), 'status' => 'ready', 'metadata' => []];
    }

    public function descriptor(MediaAsset $asset): PlaybackDescriptor
    {
        return new PlaybackDescriptor(PlaybackDescriptor::VIDEO, 'external_url', MediaAsset::TIER_OPEN, url: (string) $asset->url);
    }
}
