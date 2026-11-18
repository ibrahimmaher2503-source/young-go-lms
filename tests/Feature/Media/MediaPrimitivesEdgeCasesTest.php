<?php

declare(strict_types=1);

// T-P3-08 | ED-031, YG-GAP-VID-002 | A1 signed/linked media safety, A3 input validation, A4 localization

use App\Domain\Media\PlaybackDescriptor;
use App\Domain\Media\SourceUrl;
use App\Domain\Media\VttValidator;
use App\Domain\Media\Watermark;
use App\Models\MediaAsset;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

it('parses only public https sources and strips fragments and credentials', function (): void {
    $url = SourceUrl::https('https://Example.COM./Path/File.mp4?q=1&x=2#frag');

    expect($url)->toBe(['scheme' => 'https', 'host' => 'example.com', 'path' => '/Path/File.mp4', 'query' => 'q=1&x=2'])
        ->and(SourceUrl::https('https://8.8.8.8/media.mp4'))->toMatchArray(['host' => '8.8.8.8', 'path' => '/media.mp4'])
        ->and(SourceUrl::https('https://example.com'))->toBe(['scheme' => 'https', 'host' => 'example.com', 'path' => '', 'query' => ''])
        ->and(SourceUrl::https('https://xn--mgbh0fb.example/a'))->toMatchArray(['host' => 'xn--mgbh0fb.example']);
});

it('rejects unsafe, credentialed, private and malformed sources', function (mixed $value): void {
    expect(fn () => SourceUrl::https($value))->toThrow(ValidationException::class);
})->with([
    'http' => 'http://example.com/a.mp4',
    'scheme relative' => '//example.com/a.mp4',
    'userinfo' => 'https://user:pass@example.com/a.mp4',
    'localhost' => 'https://localhost/a.mp4',
    'dot local' => 'https://cdn.local/a.mp4',
    'dot internal' => 'https://cdn.internal/a.mp4',
    'dot localhost' => 'https://x.localhost/a.mp4',
    'single label' => 'https://intranet/a.mp4',
    'loopback' => 'https://127.0.0.1/a.mp4',
    'private 10' => 'https://10.1.2.3/a.mp4',
    'private 192' => 'https://192.168.1.5/a.mp4',
    'ipv6 loopback' => 'https://[::1]/a.mp4',
    'empty' => '   ',
    'raw newline' => "https://example.com/a\nb.mp4",
    'too long' => 'https://example.com/'.str_repeat('a', 2048),
    'null' => null,
    'array' => [['url' => 'https://example.com']],
])->group('T-P3-08', 'A3');

it('accepts valid WebVTT including BOM, CRLF, hours and overlapping cues', function (string $contents): void {
    expect(VttValidator::problem($contents))->toBeNull();
})->with([
    'basic' => "WEBVTT\n\n00:00:01.000 --> 00:00:02.500\nHello\n",
    'header text' => "WEBVTT - Arabic captions\n\n00:01.000 --> 00:02.000\nمرحبا\n",
    'bom and crlf' => "\xEF\xBB\xBFWEBVTT\r\n\r\n00:00:01.000 --> 00:00:02.000\r\nHello\r\n",
    'hours' => "WEBVTT\n\n01:02:03.004 --> 01:02:04.005\nLong\n",
    'overlap' => "WEBVTT\n\n00:00:01.000 --> 00:00:05.000\nOne\n\n00:00:02.000 --> 00:00:03.000\nTwo\n",
    'same start' => "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nOne\n\n00:00:01.000 --> 00:00:03.000\nTwo\n",
])->group('T-P3-08', 'A3');

it('rejects unsafe or malformed caption files with a reason key', function (string $contents, string $reason): void {
    expect(VttValidator::problem($contents))->toBe($reason);
})->with([
    'empty' => ['', 'size'],
    'too large' => [str_repeat('a', VttValidator::MAX_BYTES + 1), 'size'],
    'invalid utf8' => ["WEBVTT\n\n00:00:01.000 --> 00:00:02.000\n\xFF\n", 'encoding'],
    'nul byte' => ["WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi\0\n", 'encoding'],
    'lowercase signature' => ["webvtt\n\n00:00:01.000 --> 00:00:02.000\nHi\n", 'signature'],
    'no signature' => ["00:00:01.000 --> 00:00:02.000\nHi\n", 'signature'],
    'script' => ["WEBVTT\n\n00:00:01.000 --> 00:00:02.000\n<script>alert(1)</script>\n", 'markup'],
    'iframe' => ["WEBVTT\n\n00:00:01.000 --> 00:00:02.000\n<iframe src=x>\n", 'markup'],
    'javascript' => ["WEBVTT\n\n00:00:01.000 --> 00:00:02.000\njavascript:alert(1)\n", 'markup'],
    'reversed' => ["WEBVTT\n\n00:00:02.000 --> 00:00:01.000\nHi\n", 'timing'],
    'equal times' => ["WEBVTT\n\n00:00:01.000 --> 00:00:01.000\nHi\n", 'timing'],
    'out of order' => ["WEBVTT\n\n00:00:05.000 --> 00:00:06.000\nOne\n\n00:00:01.000 --> 00:00:02.000\nTwo\n", 'timing'],
    'header only' => ["WEBVTT\n\nNOTE nothing here\n", 'no_cues'],
    'invalid minute' => ["WEBVTT\n\n00:60:01.000 --> 00:60:02.000\nHi\n", 'timing'],
])->group('T-P3-08', 'A3');

it('watermarks protected and restricted playback only with a masked contact', function (): void {
    $user = (new User)->forceFill(['id' => 42, 'email' => 'sara.ahmed@example.com']);

    expect(Watermark::applies(MediaAsset::TIER_PROTECTED))->toBeTrue()
        ->and(Watermark::applies(MediaAsset::TIER_RESTRICTED))->toBeTrue()
        ->and(Watermark::applies(MediaAsset::TIER_OPEN))->toBeFalse()
        ->and(Watermark::label($user))->toStartWith('ID 000042')
        ->and(Watermark::label($user))->toContain('sa')
        ->and(Watermark::label($user))->not->toContain('sara.ahmed@example.com')
        ->and(Watermark::label($user))->not->toContain('@example.com');

    $byName = (new User)->forceFill(['id' => 7, 'email' => null, 'username' => 'omar_hassan']);
    expect(Watermark::label($byName))->toStartWith('ID 000007')->and(Watermark::label($byName))->toContain('om')->not->toContain('omar_hassan');

    $anonymous = (new User)->forceFill(['id' => 1234567, 'email' => null, 'username' => null]);
    expect(Watermark::label($anonymous))->toBe('ID 1234567');
});

it('serializes a playback descriptor without empty keys and keeps tracks immutable', function (): void {
    $expires = CarbonImmutable::parse('2027-01-02 03:04:05', 'UTC');
    $descriptor = new PlaybackDescriptor(
        PlaybackDescriptor::VIDEO, 'signed_storage', MediaAsset::TIER_PROTECTED,
        url: 'https://files.example.com/a.mp4', sandbox: 'allow-scripts', expiresAt: $expires,
    );

    expect($descriptor->toArray())->toBe([
        'type' => 'video', 'provider' => 'signed_storage', 'tier' => MediaAsset::TIER_PROTECTED,
        'url' => 'https://files.example.com/a.mp4', 'sandbox' => 'allow-scripts', 'expires_at' => $expires->toIso8601String(),
    ]);

    $tracks = [['locale' => 'ar', 'label' => 'العربية', 'default' => true, 'url' => 'https://files.example.com/a.vtt']];
    $withTracks = $descriptor->withTracks($tracks);

    expect($descriptor->tracks)->toBe([])
        ->and($withTracks->tracks)->toBe($tracks)
        ->and($withTracks->toArray()['tracks'])->toBe($tracks)
        ->and($withTracks->url)->toBe($descriptor->url);

    $minimal = new PlaybackDescriptor(PlaybackDescriptor::TEXT, 'rich_text', MediaAsset::TIER_RESTRICTED, text: '');
    expect($minimal->toArray())->toBe(['type' => 'text', 'provider' => 'rich_text', 'tier' => MediaAsset::TIER_RESTRICTED, 'text' => '']);
});
