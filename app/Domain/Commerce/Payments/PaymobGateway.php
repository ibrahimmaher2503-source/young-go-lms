<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

use App\Domain\Commerce\Contracts\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paymob (Egypt) adapter, written against the documented Intention API and unified checkout (see docs/integrations/PAYMOB.md).
 * It is inert until credentials exist (B-07): the resolver only hands it out when `PaymobCredentials::complete()`, and every
 * method that would call the network first checks that. Written from the documented API and NOT yet exercised against Paymob.
 * Secrets are read only from config, sent only in the Authorization header, and scrubbed from every message and log line.
 */
final class PaymobGateway implements PaymentGateway
{
    /** Fields concatenated, in this order, for the processed-transaction HMAC (SHA-512, hex). */
    private const array HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id',
        'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id', 'owner',
        'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];

    private const int LIST_PAGE_SIZE = 100;

    private const int MAX_LIST_PAGES = 50;

    public function __construct(private readonly PaymobCredentials $credentials) {}

    public function key(): string
    {
        return 'paymob';
    }

    public function isConfigured(): bool
    {
        return $this->credentials->complete() && ! $this->credentials->blockedAsLive();
    }

    public function supportedMethods(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', (array) config('payments.methods', [])),
            fn (string $method): bool => isset($this->credentials->integrationIds[$method]),
        ));
    }

    public function createCheckout(CheckoutIntent $intent): CheckoutSession
    {
        $this->assertConfigured();

        $integrations = [];
        foreach ($intent->methods as $method) {
            if (isset($this->credentials->integrationIds[$method])) {
                $integrations[] = is_numeric($this->credentials->integrationIds[$method]) ? (int) $this->credentials->integrationIds[$method] : $this->credentials->integrationIds[$method];
            }
        }
        if ($integrations === []) {
            throw new PaymentGatewayException('No enabled payment method has a Paymob integration.', 'no_methods');
        }

        $name = trim($intent->customerName) !== '' ? trim($intent->customerName) : 'NA';
        $parts = preg_split('/\s+/u', $name, 2) ?: [$name];

        $body = [
            'amount' => $intent->amountMinor,
            'currency' => $intent->currency,
            'payment_methods' => $integrations,
            'items' => array_map(static fn (array $item): array => [
                'name' => mb_substr($item['name'], 0, 50), 'amount' => $item['amount_minor'], 'description' => mb_substr($item['name'], 0, 255), 'quantity' => $item['quantity'],
            ], $intent->items),
            'billing_data' => [
                'first_name' => $parts[0], 'last_name' => $parts[1] ?? 'NA', 'email' => $intent->customerEmail, 'phone_number' => 'NA',
            ],
            'special_reference' => $intent->reference,
            'notification_url' => $intent->notificationUrl,
            'redirection_url' => $intent->returnUrl,
            'extras' => ['order_reference' => $intent->orderReference],
        ];

        $json = $this->send('create_checkout', fn () => Http::baseUrl($this->credentials->baseUrl)
            ->timeout($this->credentials->timeoutSeconds)
            ->withHeaders(['Authorization' => 'Token '.$this->credentials->secretKey()])
            ->acceptJson()->asJson()->post('/v1/intention/', $body));

        $clientSecret = $json['client_secret'] ?? null;
        $intentionId = $json['id'] ?? ($json['intention_order_id'] ?? null);
        if (! is_string($clientSecret) || $clientSecret === '' || ! is_scalar($intentionId)) {
            throw new PaymentGatewayException('Paymob returned an unexpected checkout response.', 'bad_response');
        }

        $url = $this->credentials->baseUrl.'/unifiedcheckout/?'.http_build_query(['publicKey' => $this->credentials->publicKey(), 'clientSecret' => $clientSecret]);
        $orderId = $json['intention_order_id'] ?? null;

        return new CheckoutSession('paymob', (string) $intentionId, is_scalar($orderId) ? (string) $orderId : null, $url);
    }

    public function verifyCallback(array $payload, ?string $signature): VerifiedPaymentEvent
    {
        if (! $this->credentials->complete()) {
            throw new InvalidPaymentSignature('Paymob is not configured; callbacks cannot be verified.');
        }
        $obj = $payload['obj'] ?? null;
        if (! is_array($obj) || $signature === null || $signature === '') {
            throw new InvalidPaymentSignature;
        }

        $flat = self::flatten($obj);
        $message = '';
        foreach (self::HMAC_FIELDS as $field) {
            $value = $flat[$field] ?? null;
            $message .= is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '');
        }
        $expected = hash_hmac('sha512', $message, $this->credentials->hmacSecret());
        if (! hash_equals($expected, strtolower($signature))) {
            throw new InvalidPaymentSignature;
        }

        return $this->normalise($obj);
    }

    public function fetchTransaction(string $transactionId): VerifiedPaymentEvent
    {
        $this->assertConfigured();
        $apiKey = $this->credentials->apiKey();
        if ($apiKey === null) {
            throw new PaymentGatewayException('Transaction lookup needs the Paymob API key.', 'no_api_key');
        }

        $auth = $this->send('auth_token', fn () => Http::baseUrl($this->credentials->baseUrl)
            ->timeout($this->credentials->timeoutSeconds)->acceptJson()->asJson()->post('/api/auth/tokens', ['api_key' => $apiKey]));
        $token = $auth['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new PaymentGatewayException('Paymob returned an unexpected authentication response.', 'bad_response');
        }

        $json = $this->send('fetch_transaction', fn () => Http::baseUrl($this->credentials->baseUrl)
            ->timeout($this->credentials->timeoutSeconds)->withToken($token)->acceptJson()
            ->get('/api/acceptance/transactions/'.rawurlencode($transactionId)));

        return $this->normalise($json);
    }

    public function listTransactions(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $apiKey = $this->credentials->apiKey();
        if (! $this->isConfigured() || $apiKey === null) {
            return [];
        }

        $auth = $this->send('auth_token', fn () => Http::baseUrl($this->credentials->baseUrl)
            ->timeout($this->credentials->timeoutSeconds)->acceptJson()->asJson()->post('/api/auth/tokens', ['api_key' => $apiKey]));
        $token = $auth['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new PaymentGatewayException('Paymob returned an unexpected authentication response.', 'bad_response');
        }

        // Pages come newest first; stop once a whole page is older than the window. Hard page cap keeps one run bounded.
        $found = [];
        for ($page = 1; $page <= self::MAX_LIST_PAGES; $page++) {
            $json = $this->send('list_transactions', fn () => Http::baseUrl($this->credentials->baseUrl)
                ->timeout($this->credentials->timeoutSeconds)->withToken($token)->acceptJson()
                ->get('/api/acceptance/transactions', ['page' => $page, 'page_size' => self::LIST_PAGE_SIZE]));
            $rows = $json['results'] ?? [];
            if (! is_array($rows) || $rows === []) {
                break;
            }

            $olderThanWindow = 0;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $createdAt = self::parseTime($row['created_at'] ?? null);
                if ($createdAt === null) {
                    continue;
                }
                if ($createdAt->lt($from)) {
                    $olderThanWindow++;
                } elseif ($createdAt->lte($to)) {
                    $found[] = $this->normalise($row);
                }
            }
            if ($olderThanWindow === count($rows) || ($json['next'] ?? null) === null) {
                break;
            }
        }

        return $found;
    }

    private static function parseTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /** The Paymob refund endpoint is not documented in docs/integrations/PAYMOB.md, so none is built from memory (rule 2); finance refunds manually. */
    public function supportsRefunds(): bool
    {
        return false;
    }

    public function refund(RefundIntent $intent): RefundResult
    {
        throw new PaymentGatewayException('Paymob refunds are not supported yet.', 'refund_unsupported');
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayNotConfigured;
        }
    }

    /**
     * Runs one HTTP call. Any failure becomes a redacted PaymentGatewayException with a status code only: no request or
     * response body is copied into messages or logs.
     *
     * @param  callable(): mixed  $call
     * @return array<string, mixed>
     */
    private function send(string $operation, callable $call): array
    {
        try {
            $response = $call();
            $status = (int) $response->status();
            $ok = (bool) $response->successful();
            $json = $ok ? $response->json() : null;
        } catch (Throwable $e) {
            $message = $this->credentials->redactor()->redact('Paymob '.$operation.' could not be reached ('.$e::class.').');
            Log::warning($message);

            throw new PaymentGatewayException($message, 'unreachable');
        }

        if (! $ok || ! is_array($json)) {
            $message = 'Paymob '.$operation.' failed with HTTP status '.$status.'.';
            Log::warning($message);

            throw new PaymentGatewayException($message, 'http_'.$status);
        }

        return $json;
    }

    /**
     * @param  array<array-key, mixed>  $obj
     */
    private function normalise(array $obj): VerifiedPaymentEvent
    {
        $order = isset($obj['order']) && is_array($obj['order']) ? $obj['order'] : [];
        $reference = $order['merchant_order_id'] ?? ($obj['merchant_order_id'] ?? null);
        $special = $obj['special_reference'] ?? ($order['special_reference'] ?? null);
        $source = isset($obj['source_data']) && is_array($obj['source_data']) ? $obj['source_data'] : [];

        // Only non-sensitive fields are kept; card number, tokens and billing data are dropped on purpose.
        $payload = [
            'id' => self::scalar($obj['id'] ?? null), 'amount_cents' => self::scalar($obj['amount_cents'] ?? null), 'currency' => self::scalar($obj['currency'] ?? null),
            'success' => (bool) ($obj['success'] ?? false), 'pending' => (bool) ($obj['pending'] ?? false), 'is_refunded' => (bool) ($obj['is_refunded'] ?? false),
            'is_voided' => (bool) ($obj['is_voided'] ?? false), 'integration_id' => self::scalar($obj['integration_id'] ?? null),
            'source_type' => self::scalar($source['type'] ?? null), 'order_id' => self::scalar($order['id'] ?? null),
        ];

        return new VerifiedPaymentEvent(
            'paymob', (string) ($obj['id'] ?? ''), self::scalar($special ?? $reference), self::scalar($order['id'] ?? null),
            (int) ($obj['amount_cents'] ?? 0), strtoupper((string) ($obj['currency'] ?? '')), (bool) ($obj['success'] ?? false),
            (bool) ($obj['pending'] ?? false), (bool) ($obj['is_refunded'] ?? false), (bool) ($obj['is_voided'] ?? false), $payload,
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            $name = $prefix.$key;
            if (is_array($value)) {
                $flat += self::flatten($value, $name.'.');
            } else {
                $flat[$name] = $value;
            }
        }

        return $flat;
    }

    private static function scalar(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
