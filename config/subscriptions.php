<?php

declare(strict_types=1);

/**
 * T-P8-08: subscription defaults (PRD 29.7, R-017). Renewal is manual: reminders go out this many days before a period
 * ends, access continues for the grace days after it, and nothing is prorated. A renewal order is accepted once the
 * current period ends within `renewal_window_days` (or has already ended). Automatic renewal is the feature flag
 * `subscriptions.auto_renew`, off by default and not built (O-024).
 */
return [
    'reminder_offsets_days' => [7, 3, 1],
    'grace_days' => 3,
    'renewal_window_days' => 7,
];
