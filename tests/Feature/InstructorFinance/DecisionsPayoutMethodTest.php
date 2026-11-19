<?php

declare(strict_types=1);

// T-DECISIONS-2 | O-012 | A1

use App\Domain\InstructorFinance\PayoutMethod;

it('allows bank, wallet and instapay for EGP and only bank for SAR', function (): void {
    expect(PayoutMethod::allowedFor('EGP'))->toBe([PayoutMethod::Bank, PayoutMethod::Wallet, PayoutMethod::Instapay])
        ->and(PayoutMethod::allowedFor('SAR'))->toBe([PayoutMethod::Bank])
        ->and(PayoutMethod::allowedFor('egp'))->toBe([PayoutMethod::Bank, PayoutMethod::Wallet, PayoutMethod::Instapay])
        ->and(PayoutMethod::allowedFor('USD'))->toBe([]);
})->group('T-DECISIONS-2', 'O-012', 'A1');

it('never allows the legacy cash and other methods for any currency', function (): void {
    expect(PayoutMethod::Cash->isAllowedFor('EGP'))->toBeFalse()
        ->and(PayoutMethod::Other->isAllowedFor('SAR'))->toBeFalse();
})->group('T-DECISIONS-2', 'O-012', 'A1');

it('accepts bank and wallet references of 3 to 64 characters and refuses a single-character bank reference', function (): void {
    expect(PayoutMethod::Bank->acceptsReference('TRX-001'))->toBeTrue()
        ->and(PayoutMethod::Bank->acceptsReference('AB'))->toBeFalse()
        ->and(PayoutMethod::Bank->acceptsReference('AB!'))->toBeFalse()
        ->and(PayoutMethod::Wallet->acceptsReference('W/12 34'))->toBeTrue()
        ->and(PayoutMethod::Wallet->acceptsReference('<script>'))->toBeFalse()
        ->and(PayoutMethod::Bank->acceptsReference(str_repeat('A', 64)))->toBeTrue()
        ->and(PayoutMethod::Bank->acceptsReference(str_repeat('A', 65)))->toBeFalse();
})->group('T-DECISIONS-2', 'O-012', 'A1');

it('requires at least 6 characters for an instapay reference', function (): void {
    expect(PayoutMethod::Instapay->acceptsReference('ABC12'))->toBeFalse()
        ->and(PayoutMethod::Instapay->acceptsReference('ABC123'))->toBeTrue()
        ->and(PayoutMethod::Instapay->referenceErrorKey())->toBe('reference_invalid_instapay')
        ->and(PayoutMethod::Wallet->referenceErrorKey())->toBe('reference_invalid_wallet')
        ->and(PayoutMethod::Bank->referenceErrorKey())->toBe('reference_invalid_bank');
})->group('T-DECISIONS-2', 'O-012', 'A1');
