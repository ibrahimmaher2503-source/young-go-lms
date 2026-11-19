<?php

declare(strict_types=1);

// T-DECISIONS-2026-11-18 | O-005, O-013, O-021, O-022, O-024 (sentry), live meet regression | A1

use App\Domain\Live\ManualMeetingProvider;
use App\Domain\Media\MediaProviders;
use App\Domain\Media\PlaybackDescriptor;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Observability\LogErrorReporter;
use App\Domain\Shared\Observability\SentryErrorReporter;
use App\Domain\Shared\Observability\ErrorReporter;
use App\Models\MediaAsset;
use Illuminate\Validation\ValidationException;

const DECISIONS_BUNNY_UUID = '9f1c2b3a-4d5e-4f60-8a7b-0c1d2e3f4a5b';

function decisionsBunnyEmbed(string $url): array
{
    return MediaProviders::for('bunny')->validate(['url' => $url]);
}

it('maps a Bunny embed link to a bunny video with the lowercased uuid and library id', function (): void {
    $result = decisionsBunnyEmbed('https://iframe.mediadelivery.net/embed/12345/'.strtoupper(DECISIONS_BUNNY_UUID));

    expect($result['provider'])->toBe('bunny')
        ->and($result['external_id'])->toBe(DECISIONS_BUNNY_UUID)
        ->and($result['metadata'])->toMatchArray(['library_id' => '12345'])
        ->and($result['protection_tier'])->toBe(MediaAsset::TIER_OPEN);
})->group('T-DECISIONS-2026-11-18', 'A1');

it('accepts the /play/ variant of a Bunny link', function (): void {
    $result = decisionsBunnyEmbed('https://iframe.mediadelivery.net/play/12345/'.DECISIONS_BUNNY_UUID);

    expect($result['provider'])->toBe('bunny')->and($result['external_id'])->toBe(DECISIONS_BUNNY_UUID);
})->group('T-DECISIONS-2026-11-18', 'A1');

it('stores the HLS playlist url for a b-cdn playlist link', function (): void {
    $url = 'https://vz-abc.b-cdn.net/'.DECISIONS_BUNNY_UUID.'/playlist.m3u8';
    $result = MediaProviders::for('bunny')->validate(['url' => $url]);

    expect($result['url'])->toBe($url)->and($result['external_id'])->toBe(DECISIONS_BUNNY_UUID);
})->group('T-DECISIONS-2026-11-18', 'A1');

it('refuses http, other hosts, a bad uuid and an extra path with media.invalid_bunny', function (string $url): void {
    expect(fn () => decisionsBunnyEmbed($url))->toThrow(ValidationException::class);
})->with([
    'http scheme' => ['http://iframe.mediadelivery.net/embed/12345/'.DECISIONS_BUNNY_UUID],
    'other host' => ['https://example.test/embed/12345/'.DECISIONS_BUNNY_UUID],
    'bad uuid' => ['https://iframe.mediadelivery.net/embed/12345/not-a-uuid'],
    'extra path' => ['https://iframe.mediadelivery.net/embed/12345/'.DECISIONS_BUNNY_UUID.'/extra'],
])->group('T-DECISIONS-2026-11-18', 'A1');

it('describes a stored bunny embed as an embed with the iframe url and the sandbox', function (): void {
    $asset = new MediaAsset([
        'provider' => 'bunny',
        'external_id' => DECISIONS_BUNNY_UUID,
        'metadata' => ['library_id' => '12345'],
    ]);

    $descriptor = MediaProviders::for('bunny')->descriptor($asset);

    expect($descriptor->type)->toBe(PlaybackDescriptor::EMBED)
        ->and($descriptor->embedUrl)->toBe('https://iframe.mediadelivery.net/embed/12345/'.DECISIONS_BUNNY_UUID)
        ->and($descriptor->sandbox)->toBe(config('media.iframe_sandbox'));
})->group('T-DECISIONS-2026-11-18', 'A1');

it('accepts a plain https meet link for provider meet and still refuses http and credentials', function (): void {
    $provider = new ManualMeetingProvider;

    expect($provider->validateUrl('meet', 'https://meet.google.com/abc-defg-hij'))->toBe('https://meet.google.com/abc-defg-hij');
    expect(fn () => $provider->validateUrl('meet', 'http://meet.google.com/abc-defg-hij'))->toThrow(ValidationException::class);
    expect(fn () => $provider->validateUrl('meet', 'https://user:pass@meet.google.com/abc-defg-hij'))->toThrow(ValidationException::class);
})->group('T-DECISIONS-2026-11-18', 'A1');

it('keeps Saudi Arabia closed while Paymob KSA is the configured gateway', function (): void {
    expect(config('markets.SA.gateway'))->toBe('paymob')
        ->and(config('payments.gateway_by_currency.SAR'))->toBe('paymob')
        ->and(config('markets.SA.active'))->toBeFalse()
        ->and(Market::SaudiArabia->isActive())->toBeFalse();
})->group('T-DECISIONS-2026-11-18', 'A1');

it('configures Amazon SES as a mailer while the testing default stays array', function (): void {
    expect(config('mail.mailers.ses.transport'))->toBe('ses')
        ->and(config('mail.default'))->toBe('array');
})->group('T-DECISIONS-2026-11-18', 'A1');

it('reports errors to the log reporter without a Sentry DSN and to Sentry with one', function (): void {
    config(['observability.error_reporter' => 'sentry', 'observability.sentry.dsn' => null]);
    app()->forgetInstance(ErrorReporter::class);

    expect(config('observability.error_reporter'))->toBe('sentry')
        ->and(app(ErrorReporter::class))->toBeInstanceOf(LogErrorReporter::class);

    config(['observability.sentry.dsn' => 'https://public@example.test/1']);
    app()->forgetInstance(ErrorReporter::class);

    expect(app(ErrorReporter::class))->toBeInstanceOf(SentryErrorReporter::class);

    app(ErrorReporter::class)->report(new RuntimeException('decisions probe'));
})->group('T-DECISIONS-2026-11-18', 'A1');
