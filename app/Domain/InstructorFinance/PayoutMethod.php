<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

/**
 * How finance paid the instructor OUTSIDE the system. Only the method name is recorded, never account details.
 * O-012 (2026-11-18): Egypt (EGP) pays by bank transfer, mobile wallet or Instapay; Saudi Arabia (SAR) by bank transfer only.
 * `Cash` and `Other` are LEGACY values kept so old paid lines still render; they can no longer be recorded.
 */
enum PayoutMethod: string
{
    /** Bank transfer (stored value `bank`, label "bank transfer"). */
    case Bank = 'bank';
    case Wallet = 'wallet';
    case Instapay = 'instapay';
    case Cash = 'cash';
    case Other = 'other';

    /** @var array<string, list<self>> */
    private const array BY_CURRENCY = [
        'EGP' => [self::Bank, self::Wallet, self::Instapay],
        'SAR' => [self::Bank],
    ];

    /** @return list<self> Methods finance may record for a settlement in this currency (empty for an unknown currency). */
    public static function allowedFor(string $currency): array
    {
        return self::BY_CURRENCY[strtoupper($currency)] ?? [];
    }

    public function isAllowedFor(string $currency): bool
    {
        return in_array($this, self::allowedFor($currency), true);
    }

    /** Lang key of the reference error for this method (see lang settlements.reference_invalid_*). */
    public function referenceErrorKey(): string
    {
        return match ($this) {
            self::Instapay => 'reference_invalid_instapay',
            self::Wallet => 'reference_invalid_wallet',
            default => 'reference_invalid_bank',
        };
    }

    /** Whether the payment reference (already trimmed) is valid for this method: a bank/wallet transaction id of 3..64, an Instapay one of 6..64 (letters, digits, - _ / . and inner spaces). */
    public function acceptsReference(string $reference): bool
    {
        $min = $this === self::Instapay ? 6 : 3;

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_\/. ]{'.($min - 1).',63}$/', $reference) === 1;
    }
}
