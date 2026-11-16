<?php

declare(strict_types=1);

use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Markets\MarketResolver;

it('refuses checkout in a disabled market', function () {
    expect((new MarketResolver)->checkout('EG'))->toBe(Market::Egypt);
    expect(fn () => (new MarketResolver)->checkout('SA'))->toThrow(DomainException::class);
})->group('T-P0-06', 'A3', 'D-009', 'I15');
