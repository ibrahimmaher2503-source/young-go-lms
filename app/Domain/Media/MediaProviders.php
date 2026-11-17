<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Domain\Media\Contracts\VideoDeliveryProvider;
use App\Domain\Media\Providers\AllowlistedIframeProvider;
use App\Domain\Media\Providers\ExternalUrlProvider;
use App\Domain\Media\Providers\PrivateFileProvider;
use App\Domain\Media\Providers\RichTextProvider;
use App\Domain\Media\Providers\VimeoProvider;
use App\Domain\Media\Providers\YouTubeProvider;
use Illuminate\Validation\ValidationException;

/** The one place that knows which drivers exist. Swapping the private streaming provider (O-005) happens here. */
final class MediaProviders
{
    /** @return array<string, VideoDeliveryProvider> */
    public static function all(): array
    {
        $providers = [
            new PrivateFileProvider('signed_storage', 'video', PlaybackDescriptor::VIDEO, ['mp4', 'webm', 'm4v', 'mov']),
            new PrivateFileProvider('audio', 'audio', PlaybackDescriptor::AUDIO, ['mp3', 'm4a', 'ogg', 'wav']),
            new PrivateFileProvider('document', 'document', PlaybackDescriptor::DOCUMENT, ['pdf']),
            new PrivateFileProvider('image', 'image', PlaybackDescriptor::IMAGE, ['png', 'jpg', 'jpeg', 'webp']),
            new VimeoProvider,
            new YouTubeProvider,
            new ExternalUrlProvider,
            new AllowlistedIframeProvider,
            new RichTextProvider,
        ];

        $byKey = [];
        foreach ($providers as $provider) {
            $byKey[$provider->key()] = $provider;
        }

        return $byKey;
    }

    public static function for(mixed $key): VideoDeliveryProvider
    {
        $all = self::all();

        if (! is_string($key) || ! isset($all[$key])) {
            throw ValidationException::withMessages(['provider' => __('media.unknown_provider')]);
        }

        return $all[$key];
    }
}
