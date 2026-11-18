<?php

declare(strict_types=1);

return [
    'terms_version' => env('TERMS_VERSION'),
    'privacy_version' => env('PRIVACY_VERSION'),
    'login_max_attempts' => 5,
    'learner_session_limit' => (int) env('LEARNER_SESSION_LIMIT', 2),

    // ED-016 / YG-GAP-SEC-003. Set TWO_FACTOR_ENFORCED=false only for local development and automated tests.
    'two_factor_enforced' => (bool) env('TWO_FACTOR_ENFORCED', true),
    'two_factor_required_roles' => ['super_admin', 'admin', 'finance'],
];
