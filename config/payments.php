<?php

declare(strict_types=1);

/**
 * T-P8-02: payment gateway wiring. Secrets come only from the environment and are never stored in the database or logged.
 * Paymob credentials are not supplied yet (blocker B-07): with none set the app uses the Fake gateway locally and in tests
 * and the refusing Null gateway everywhere else.
 */
return [
    // auto | fake | null. `fake` is honoured only in local and testing environments.
    'driver' => env('PAYMENTS_GATEWAY', 'auto'),

    // Live collection stays closed until the owner gates are met (AGENTS rule 3). A live secret key is ignored while false.
    'live_enabled' => (bool) env('PAYMENTS_LIVE_ENABLED', false),

    // Gateway per currency (O-006, O-013 decided 2026-11-18): Paymob serves Egypt (EGP: cards, wallets; Instapay is a manual proof)
    // and Paymob KSA serves Saudi (SAR). Both stay inert without credentials; the Saudi market itself stays disabled (markets.SA.active).
    'gateway_by_currency' => ['EGP' => 'paymob', 'SAR' => 'paymob'],

    // Checkout methods offered to the payer, in display order. Cards and wallets first (T-P8-02).
    'methods' => ['card', 'wallet'],

    // Minutes an unpaid checkout session stays reusable before a new one is created (working default).
    'attempt_ttl_minutes' => 30,

    // Hours an unpaid order with no open payment or proof stays pending before it is cancelled and its coupon use released (working default, T-P8-12).
    'order_ttl_hours' => 24,

    // Paths on this site given to the gateway. The return page shows status only; the callback is the single fulfilment path (T-P8-03).
    'return_path' => '/checkout/return',
    'callback_path' => '/webhooks/paymob',

    'paymob' => [
        'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
        'secret_key' => env('PAYMOB_SECRET_KEY'),
        'public_key' => env('PAYMOB_PUBLIC_KEY'),
        'api_key' => env('PAYMOB_API_KEY'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        // Paymob integration (payment method) ids, one per method key in `methods`.
        'integration_ids' => [
            'card' => env('PAYMOB_INTEGRATION_CARD'),
            'wallet' => env('PAYMOB_INTEGRATION_WALLET'),
        ],
        'timeout_seconds' => 15,
    ],
];
