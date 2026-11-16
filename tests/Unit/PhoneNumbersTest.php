<?php

declare(strict_types=1);

use App\Domain\Shared\Phone\PhoneNumbers;

it('normalizes valid market numbers for storage', function (string $local, string $region, string $canonical) {
    expect(PhoneNumbers::normalize($local, $region))->toBe($canonical);
})->with([
    ['01001234567', 'EG', '+201001234567'],
    ['0501234567', 'SA', '+966501234567'],
])->group('T-P0-07', 'A4', 'ED-019');

it('rejects invalid and out-of-market numbers', function (string $input) {
    expect(fn () => PhoneNumbers::normalize($input, 'EG'))->toThrow(InvalidArgumentException::class);
})->with(['123', '+966501234567'])->group('T-P0-07', 'A4', 'ED-019');

it('displays stored numbers in national format', function () {
    expect(PhoneNumbers::display('+201001234567'))->toBe('010 01234567');
})->group('T-P0-07', 'A4', 'ED-019');
