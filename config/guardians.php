<?php

declare(strict_types=1);

return [
    // O-015 (PO decision 2026-11-18): a guardian is "verified" when the parent account has a verified email AND has confirmed a
    // 6-digit code sent to that email. Working limits below. The code is stored hashed, never in clear text.
    'otp' => [
        'expires_minutes' => 10,
        'max_attempts' => 5,
        // Shortest time between two codes, and most codes per hour, for one guardian.
        'resend_seconds' => 60,
        'max_sends_per_hour' => 5,
    ],

    // The minors' protected features (the lesson assistant today) also need a verified guardian. Keep true in every real environment;
    // only the automated suite of the older minors fixtures switches it off (phpunit.xml). AI stays off for under-18s anyway (O-015).
    'require_verification_for_ai' => (bool) env('GUARDIAN_VERIFICATION_REQUIRED_FOR_AI', true),
];
