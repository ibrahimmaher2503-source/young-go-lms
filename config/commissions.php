<?php

declare(strict_types=1);

/**
 * T-P8-09: commission fallbacks used when no `course_commission_rules` row applies to a sale. The owner has not approved any
 * rate (O-008 individual percentage, O-009 bundle rule, O-010 fixed subscription amount, O-011 commissionable base), so every
 * fallback is ZERO: a sale with no rule still writes a ledger entry (amount 0, rule_source `config_default`) so finance can see
 * the uncovered sale, and real rates are set as rules. Rates are basis points (2500 = 25%). Subscription is a fixed amount in
 * minor units per student-course-period.
 */
return [
    'default_rate_bps' => ['individual' => 0, 'bundle' => 0],
    'default_subscription_fixed_minor' => 0,
];
