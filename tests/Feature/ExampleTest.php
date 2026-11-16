<?php

declare(strict_types=1);
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

it('boots the health endpoint without external services', function () {
    $this->get('/up')->assertOk();
})->group('T-P0-03', 'A1', 'YG-GAP-QA-001');

it('blocks accidental outbound HTTP in tests', function () {
    expect(fn () => Http::get('https://example.invalid'))
        ->toThrow(StrayRequestException::class);
})->group('T-P0-03', 'YG-GAP-QA-002');
