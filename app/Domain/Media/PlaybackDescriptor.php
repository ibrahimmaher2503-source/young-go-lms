<?php

declare(strict_types=1);

namespace App\Domain\Media;

use Carbon\CarbonInterface;

/** What a player needs to show one lesson's media. Deliberately carries nothing an AI feature could use as a source. */
final readonly class PlaybackDescriptor
{
    public const string VIDEO = 'video';

    public const string AUDIO = 'audio';

    public const string DOCUMENT = 'document';

    public const string IMAGE = 'image';

    public const string EMBED = 'embed';

    public const string TEXT = 'text';

    public function __construct(
        public string $type,
        public string $provider,
        public string $tier,
        public ?string $url = null,
        public ?string $embedUrl = null,
        public ?string $sandbox = null,
        public ?string $text = null,
        public ?CarbonInterface $expiresAt = null,
        /** @var list<array{locale: string, label: string, default: bool, url: string}> */
        public array $tracks = [],
    ) {}

    /** @param  list<array{locale: string, label: string, default: bool, url: string}>  $tracks */
    public function withTracks(array $tracks): self
    {
        return new self($this->type, $this->provider, $this->tier, $this->url, $this->embedUrl, $this->sandbox, $this->text, $this->expiresAt, $tracks);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type, 'provider' => $this->provider, 'tier' => $this->tier, 'url' => $this->url,
            'embed_url' => $this->embedUrl, 'sandbox' => $this->sandbox, 'text' => $this->text,
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'tracks' => $this->tracks === [] ? null : $this->tracks,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
