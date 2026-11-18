<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

/** Reduces a callback payload to what is safe to store: sensitive keys are masked and token-like strings are redacted. */
final readonly class PaymentPayloadScrubber
{
    private const string SENSITIVE_KEYS = '/pan|card|cvv|cvc|token|secret|hmac|signature|password|authorization|billing|email|phone|iban|account|api_?key/i';

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function scrub(array $payload, int $depth = 0): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEYS, $key) === 1) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = $depth >= 4 ? '[TRUNCATED]' : $this->scrub($value, $depth + 1);
            } elseif (is_string($value)) {
                $clean[$key] = mb_substr((new SecretRedactor([]))->redact($value), 0, 500);
            } else {
                $clean[$key] = is_scalar($value) || $value === null ? $value : '[REDACTED]';
            }
        }

        return $clean;
    }
}
