<?php

declare(strict_types=1);

return [
    // Hidden decoy field: humans never fill it, so any value means a bot and the submission is dropped silently.
    'honeypot_field' => 'website',

    // Version of the newsletter consent wording stored with each subscription. The wording itself is a placeholder
    // pending owner/counsel approval (O-025, YG-GAP-PRV-004); bump the version whenever the wording changes.
    'newsletter_consent_version' => env('NEWSLETTER_CONSENT_VERSION', 'newsletter-v1-draft'),

    // Where a subscription form was shown. Unknown values fall back to the first entry.
    'newsletter_sources' => ['page', 'contact', 'footer', 'home'],

    // Public spam limits per client address.
    'rate_limit' => [
        'per_minute' => 3,
        'per_hour' => 10,
    ],
];
