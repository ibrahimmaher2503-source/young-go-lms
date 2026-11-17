<?php

declare(strict_types=1);

namespace App\Domain\Media\Providers;

use App\Domain\Media\Contracts\VideoDeliveryProvider;
use App\Domain\Media\PlaybackDescriptor;
use App\Domain\Media\SourceUrl;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

/** T2 restricted: a private Vimeo video limited to the platform's domains in Vimeo's own settings. */
final class VimeoProvider implements VideoDeliveryProvider
{
    public function key(): string
    {
        return 'vimeo';
    }

    public function kind(): string
    {
        return 'video';
    }

    public function tier(): string
    {
        return MediaAsset::TIER_RESTRICTED;
    }

    public function validate(array $source): array
    {
        $url = SourceUrl::https($source['url'] ?? null);
        if (! in_array($url['host'], ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)
            || preg_match('#^/(?:video/)?(\d{6,12})(?:/([A-Za-z0-9]{8,12}))?/?$#', $url['path'], $match) !== 1) {
            throw ValidationException::withMessages(['url' => __('media.invalid_vimeo')]);
        }

        $hash = $match[2] ?? null;
        if ($hash === null && preg_match('/(?:^|&)h=([A-Za-z0-9]{8,12})(?:&|$)/', $url['query'], $query) === 1) {
            $hash = $query[1];
        }

        return [
            'provider' => 'vimeo', 'kind' => 'video', 'external_id' => $match[1], 'url' => null,
            'protection_tier' => $this->tier(), 'status' => 'ready', 'metadata' => $hash === null ? [] : ['hash' => $hash],
        ];
    }

    public function descriptor(MediaAsset $asset): PlaybackDescriptor
    {
        $hash = $asset->metadata['hash'] ?? null;
        $embed = 'https://player.vimeo.com/video/'.$asset->external_id.'?dnt=1'.(is_string($hash) ? '&h='.$hash : '');

        return new PlaybackDescriptor(PlaybackDescriptor::EMBED, 'vimeo', MediaAsset::TIER_RESTRICTED, embedUrl: $embed, sandbox: Config::string('media.iframe_sandbox'));
    }
}
