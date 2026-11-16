<?php

declare(strict_types=1);

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Time\LocalizedDate;

it('uses market and user timezones and localized month names without mutating the instant', function () {
    expect(config('app.timezone'))->toBe('UTC');
    $instant = new DateTimeImmutable('2026-01-01T22:30:00+00:00');

    expect(LocalizedDate::format($instant, Market::Egypt, 'ar', 'dd MMMM yyyy HH:mm'))
        ->toBe('02 يناير 2026 00:30');
    expect(LocalizedDate::format($instant, Market::Egypt, 'en', 'dd MMMM yyyy HH:mm', 'UTC'))
        ->toBe('01 January 2026 22:30');
    expect($instant->getTimezone()->getName())->toBe('+00:00');
})->group('T-P0-06', 'ED-032', 'I14');
