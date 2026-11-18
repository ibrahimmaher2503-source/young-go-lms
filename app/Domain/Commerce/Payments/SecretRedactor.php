<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

/** Removes known secret values from any text before it reaches an exception message, a log line or the database. */
final readonly class SecretRedactor
{
    /** @param  list<string|null>  $secrets */
    public function __construct(private array $secrets) {}

    public function redact(string $text): string
    {
        foreach ($this->secrets as $secret) {
            if ($secret !== null && $secret !== '') {
                $text = str_replace($secret, '[REDACTED]', $text);
            }
        }

        // Defence in depth: Authorization values and Paymob-style key prefixes.
        $text = (string) preg_replace('/(Token|Bearer)\s+[A-Za-z0-9._\-]+/i', '$1 [REDACTED]', $text);

        return (string) preg_replace('/egy_(?:sk|pk)_(?:test|live)_[A-Za-z0-9]+/', '[REDACTED]', $text);
    }
}
