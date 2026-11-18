<?php

declare(strict_types=1);

/**
 * Commission fallbacks used when no `course_commission_rules` row applies to a sale (decided 2026-11-18, PO):
 *  - O-008: 70% to an independent instructor, 30% to the platform. Per-instructor and per-course rules override. A course
 *    without an owner instructor (academy-owned) earns nothing for instructors.
 *  - O-009: bundles are allocated proportionally to the list price of the member courses, then the same rate applies.
 *  - O-010: a subscription pays the instructor 70% of the period price, split equally across the plan's courses, per
 *    student-course-period. `default_subscription_fixed_minor` (0 = off) is an optional fixed amount that replaces the rate.
 *  - O-011: the base is net of discount and excludes VAT; the platform bears gateway fees; refunds reverse proportionally.
 *  - O-012: monthly settlement; a batch pays an instructor only when the net reaches the minimum payout for its currency
 *    (minor units: 500 EGP, 100 SAR); the finance user who creates a batch cannot approve it (super_admin may).
 * Rates are basis points (7000 = 70%). Legal/accounting verification is still advised (docs/DECISIONS_2026-11-18.md).
 */
return [
    'default_rate_bps' => ['individual' => 7000, 'bundle' => 7000, 'subscription' => 7000],
    'default_subscription_fixed_minor' => 0,

    'settlement' => [
        'frequency' => 'monthly',
        'minimum_payout_minor' => ['EGP' => (int) env('SETTLEMENT_MIN_PAYOUT_EGP_MINOR', 50000), 'SAR' => (int) env('SETTLEMENT_MIN_PAYOUT_SAR_MINOR', 10000)],
        'four_eyes' => filter_var(env('SETTLEMENT_FOUR_EYES', true), FILTER_VALIDATE_BOOLEAN),
        'four_eyes_bypass_role' => 'super_admin',
    ],
];
