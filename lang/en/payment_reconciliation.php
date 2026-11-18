<?php

declare(strict_types=1);

// T-P8-11: daily payment reconciliation report for finance.
return [
    'nav' => 'Payment reconciliation',
    'title' => 'Payment reconciliation',
    'report_only' => 'This report compares our payments with the payment gateway each day. It only lists differences; it never changes an order or access.',
    'last_run' => 'Last run',
    'never_run' => 'Reconciliation has not run yet.',
    'run_date' => 'Day reconciled',
    'transactions' => 'Gateway transactions',
    'matched' => 'Matched',
    'open' => 'Open mismatches',
    'mismatches' => 'Mismatches',
    'filter' => 'Show',
    'all' => 'All',
    'empty' => 'No mismatches to show.',
    'found' => 'Found',
    'type' => 'Type',
    'reason' => 'Reason',
    'reference' => 'Payment reference',
    'order' => 'Order',
    'gateway_amount' => 'Gateway amount',
    'local_amount' => 'Our amount',
    'state' => 'Status',
    'actions' => 'Actions',
    'mark_reviewed' => 'Mark reviewed',
    'reviewed_done' => 'Marked as reviewed.',
    'denied' => 'You are not allowed to review mismatches.',
    'limit_note' => 'Showing up to :limit most recent entries.',
    'status' => [
        'open' => 'Open',
        'reviewed' => 'Reviewed',
    ],
    'types' => [
        'unmatched_transaction' => 'Transaction we do not know',
        'amount_mismatch' => 'Amount differs',
        'status_mismatch' => 'Paid status differs',
        'missing_at_gateway' => 'Missing at the gateway',
        'refunded_at_gateway' => 'Refunded at the gateway',
    ],
    'reasons' => [
        'no_attempt_for_reference' => 'No payment attempt carries this reference.',
        'amount_or_currency_differs' => 'Amount or currency differs from our record.',
        'paid_at_gateway_not_locally' => 'Paid at the gateway, not paid in our records.',
        'failed_at_gateway_paid_locally' => 'Failed at the gateway, paid in our records.',
        'not_in_gateway_listing' => 'Paid in our records, not in the gateway listing.',
        'refunded' => 'Refunded at the gateway.',
        'voided' => 'Voided at the gateway.',
    ],
];
