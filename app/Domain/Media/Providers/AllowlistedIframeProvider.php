<?php

declare(strict_types=1);

namespace App\Domain\Media\Providers;

use App\Domain\Media\Contracts\VideoDeliveryProvider;
use App\Domain\Media\PlaybackDescriptor;
use App\Domain\Media\SourceUrl;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

/** T3 open: an embed from a host the owner approved in config/media.php. Always sandboxed. */
final class AllowlistedIframeProvider implements VideoDeliveryProvider
{
    public function key(): string
    {
        return 'iframe';
    }

    public function kind(): string
    {
        return 'embed';
    }

    public function tier(): string
    {
        return MediaAsset::TIER_OPEN;
    }

    public function validate(array $source): array
    {
        $url = SourceUrl::https($source['url'] ?? null);
        $allowed = array_map('strtolower', array_filter(Config::array('media.iframe_allowlist'), 'is_string'));

        if (! in_array($url['host'], $allowed, true)) {
            throw ValidationException::withMessages(['url' => __('media.host_not_allowed')]);
        }

        $clean = 'https://'.$url['host'].$url['path'].($url['query'] === '' ? '' : '?'.$url['query']);

        return ['provider' => 'iframe', 'kind' => 'embed', 'external_id' => null, 'url' => $clean, 'protection_tier' => $this->tier(), 'status' => 'ready', 'metadata' => []];
    }

    public function descriptor(MediaAsset $asset): PlaybackDescriptor
    {
        // The allowlist is checked again at playback so removing a host takes effect for existing lessons.
        $host = strtolower((string) parse_url((string) $asset->url, PHP_URL_HOST));
        if (! in_array($host, array_map('strtolower', array_filter(Config::array('media.iframe_allowlist'), 'is_string')), true)) {
            throw ValidationException::withMessages(['url' => __('media.host_not_allowed')]);
        }

        return new PlaybackDescriptor(PlaybackDescriptor::EMBED, 'iframe', MediaAsset::TIER_OPEN, embedUrl: (string) $asset->url, sandbox: Config::string('media.iframe_sandbox'));
    }
}
