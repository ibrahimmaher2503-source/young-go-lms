<?php

declare(strict_types=1);

namespace App\Domain\Media\Providers;

use App\Domain\Media\Contracts\VideoDeliveryProvider;
use App\Domain\Media\PlaybackDescriptor;
use App\Models\MediaAsset;
use Illuminate\Validation\ValidationException;

/**
 * T1: text that lives in our own database. Plain text only until the shared HTML sanitizer exists (T-P3-15);
 * the player must escape it.
 */
final class RichTextProvider implements VideoDeliveryProvider
{
    private const int MAX = 20000;

    public function key(): string
    {
        return 'rich_text';
    }

    public function kind(): string
    {
        return 'text';
    }

    public function tier(): string
    {
        return MediaAsset::TIER_PROTECTED;
    }

    public function validate(array $source): array
    {
        $text = $source['text'] ?? null;
        if (! is_string($text) || trim($text) === '' || mb_strlen($text) > self::MAX) {
            throw ValidationException::withMessages(['text' => __('media.invalid_text')]);
        }

        return ['provider' => 'rich_text', 'kind' => 'text', 'protection_tier' => $this->tier(), 'status' => 'ready', 'metadata' => ['text' => trim($text)]];
    }

    public function descriptor(MediaAsset $asset): PlaybackDescriptor
    {
        return new PlaybackDescriptor(PlaybackDescriptor::TEXT, 'rich_text', MediaAsset::TIER_PROTECTED, text: (string) ($asset->metadata['text'] ?? ''));
    }
}
