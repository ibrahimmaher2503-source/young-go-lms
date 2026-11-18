<?php

declare(strict_types=1);

namespace App\Domain\Media\Providers;

use App\Domain\Media\Contracts\VideoDeliveryProvider;
use App\Domain\Media\PlaybackDescriptor;
use App\Domain\Media\SourceUrl;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

/**
 * Bunny Stream (O-005, decided 2026-11-18). Accepts an embed/play link (`iframe.mediadelivery.net/{embed|play}/{library}/{video}`)
 * or an HLS playlist link (`{pull-zone}.b-cdn.net/{video}/playlist.m3u8`). No API key or token is read or stored here. A link alone
 * proves no token authentication or domain lock, so the asset is classified open (T3) like Vimeo until protection is verified.
 */
final class BunnyStreamProvider implements VideoDeliveryProvider
{
    private const string GUID = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function key(): string
    {
        return 'bunny';
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
        $fail = static fn (): ValidationException => ValidationException::withMessages(['url' => __('media.invalid_bunny')]);

        if ($url['host'] === 'iframe.mediadelivery.net'
            && preg_match('#^/(?:embed|play)/(\d{1,10})/('.self::GUID.')/?$#', $url['path'], $match) === 1) {
            return [
                'provider' => 'bunny', 'kind' => 'video', 'external_id' => strtolower($match[2]), 'url' => null,
                'protection_tier' => $this->tier(), 'status' => 'ready', 'metadata' => ['library_id' => $match[1]],
            ];
        }

        if (preg_match('/^[a-z0-9-]{1,63}\.b-cdn\.net$/', $url['host']) === 1
            && preg_match('#^/('.self::GUID.')/playlist\.m3u8$#', $url['path'], $match) === 1) {
            return [
                'provider' => 'bunny', 'kind' => 'video', 'external_id' => strtolower($match[1]),
                'url' => 'https://'.$url['host'].'/'.strtolower($match[1]).'/playlist.m3u8',
                'protection_tier' => $this->tier(), 'status' => 'ready', 'metadata' => ['delivery' => 'hls'],
            ];
        }

        throw $fail();
    }

    public function descriptor(MediaAsset $asset): PlaybackDescriptor
    {
        $libraryId = $asset->metadata['library_id'] ?? null;
        if (is_string($libraryId) && $libraryId !== '') {
            return new PlaybackDescriptor(
                PlaybackDescriptor::EMBED, 'bunny', $this->tier(),
                embedUrl: 'https://iframe.mediadelivery.net/embed/'.$libraryId.'/'.$asset->external_id, sandbox: Config::string('media.iframe_sandbox'),
            );
        }

        return new PlaybackDescriptor(PlaybackDescriptor::VIDEO, 'bunny', $this->tier(), url: $asset->url);
    }
}
