<?php

declare(strict_types=1);

namespace App\Domain\Media\Contracts;

use App\Domain\Media\PlaybackDescriptor;
use App\Models\MediaAsset;
use Illuminate\Validation\ValidationException;

/**
 * One media source type. Course and player code only talks to this contract, so a provider change
 * (the private streaming provider is still O-005) never touches the course engine.
 */
interface VideoDeliveryProvider
{
    /** Stored in media_assets.provider. */
    public function key(): string;

    /** video, audio, document, image, text or embed. */
    public function kind(): string;

    /** Protection tier (T1 protected, T2 restricted, T3 open) every asset of this provider gets. */
    public function tier(): string;

    /**
     * Validates the submitted source and returns the media_assets attributes to store.
     *
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $source): array;

    /** Playback details for the lesson's player. Protected providers return short-lived signed links. */
    public function descriptor(MediaAsset $asset): PlaybackDescriptor;
}
