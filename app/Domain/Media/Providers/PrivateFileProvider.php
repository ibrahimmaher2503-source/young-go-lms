<?php

declare(strict_types=1);

namespace App\Domain\Media\Providers;

use App\Domain\Media\Contracts\VideoDeliveryProvider;
use App\Domain\Media\PlaybackDescriptor;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * T1 protected files on the private disk (video, audio, documents, images). The file is never linked directly:
 * the player gets a signed, short-lived link. Also the local driver until the private streaming provider (O-005) is chosen.
 */
final class PrivateFileProvider implements VideoDeliveryProvider
{
    /** @param list<string> $extensions */
    public function __construct(
        private readonly string $key,
        private readonly string $kind,
        private readonly string $type,
        private readonly array $extensions,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function tier(): string
    {
        return MediaAsset::TIER_PROTECTED;
    }

    public function validate(array $source): array
    {
        $path = $source['path'] ?? null;
        $fail = static fn (string $message) => ValidationException::withMessages(['path' => __($message)]);

        if (! is_string($path) || $path === '' || strlen($path) > 255
            || preg_match('#^[A-Za-z0-9][A-Za-z0-9._\-/]*$#', $path) !== 1 || str_contains($path, '..') || str_contains($path, '//')) {
            throw $fail('media.invalid_path');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, $this->extensions, true)) {
            throw $fail('media.invalid_type');
        }

        if (! Storage::disk(Config::string('media.disk'))->exists($path)) {
            throw $fail('media.file_missing');
        }

        return ['provider' => $this->key, 'kind' => $this->kind, 'path' => $path, 'protection_tier' => $this->tier(), 'status' => 'ready', 'metadata' => ['extension' => $extension]];
    }

    public function descriptor(MediaAsset $asset): PlaybackDescriptor
    {
        $expires = now()->addSeconds(Config::integer('media.signed_ttl'));

        return new PlaybackDescriptor(
            $this->type, $this->key, MediaAsset::TIER_PROTECTED,
            url: URL::temporarySignedRoute('media.play', $expires, ['asset' => $asset->getKey()]),
            expiresAt: $expires,
        );
    }
}
