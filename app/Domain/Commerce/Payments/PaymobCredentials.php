<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

/**
 * Paymob settings read from config (which reads the environment). Secrets are never printed: var_dump, logs and
 * serialization show nothing, and `redactor()` scrubs them from any text.
 */
final readonly class PaymobCredentials
{
    /**
     * @param  array<string, string>  $integrationIds  method key => Paymob integration id
     */
    public function __construct(
        public string $baseUrl,
        private ?string $secretKey,
        private ?string $publicKey,
        private ?string $apiKey,
        private ?string $hmacSecret,
        public array $integrationIds,
        public int $timeoutSeconds,
    ) {}

    public static function fromConfig(): self
    {
        $ids = [];
        foreach ((array) config('payments.paymob.integration_ids', []) as $method => $id) {
            if (is_string($id) && trim($id) !== '') {
                $ids[(string) $method] = trim($id);
            }
        }

        return new self(
            rtrim((string) config('payments.paymob.base_url', 'https://accept.paymob.com'), '/'),
            self::clean(config('payments.paymob.secret_key')),
            self::clean(config('payments.paymob.public_key')),
            self::clean(config('payments.paymob.api_key')),
            self::clean(config('payments.paymob.hmac_secret')),
            $ids,
            max(1, (int) config('payments.paymob.timeout_seconds', 15)),
        );
    }

    /** Checkout needs the secret and public keys and at least one integration; callbacks need the HMAC secret. */
    public function complete(): bool
    {
        return $this->secretKey !== null && $this->publicKey !== null && $this->hmacSecret !== null && $this->integrationIds !== [];
    }

    /** A live key stays closed until the owner gate `payments.live_enabled` is on (AGENTS rule 3). */
    public function blockedAsLive(): bool
    {
        return $this->secretKey !== null && str_contains($this->secretKey, '_live_') && ! (bool) config('payments.live_enabled', false);
    }

    public function secretKey(): string
    {
        return $this->secretKey ?? throw new PaymentGatewayNotConfigured;
    }

    public function publicKey(): string
    {
        return $this->publicKey ?? throw new PaymentGatewayNotConfigured;
    }

    public function hmacSecret(): string
    {
        return $this->hmacSecret ?? throw new PaymentGatewayNotConfigured;
    }

    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    public function redactor(): SecretRedactor
    {
        return new SecretRedactor([$this->secretKey, $this->publicKey, $this->apiKey, $this->hmacSecret]);
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'secrets' => '[REDACTED]'];
    }

    public function __serialize(): array
    {
        return [];
    }

    private static function clean(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
