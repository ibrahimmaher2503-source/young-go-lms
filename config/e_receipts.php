<?php

declare(strict_types=1);

/**
 * T-P8-06: receipts and the e-receipt adapter (YG-GAP-PAY-005). Whether Egyptian e-receipt/e-invoice submission is mandatory,
 * and who performs it, is the open owner item O-023, so the adapter is `null` (disabled) and no submission is ever faked.
 * Seller tax data is UNKNOWN until the accountant supplies it; it is copied into each receipt snapshot as given (null stays null).
 */
return [
    // null | fake. `fake` is honoured only in local and testing environments.
    'driver' => env('E_RECEIPT_DRIVER', 'null'),

    'seller' => [
        'name' => env('RECEIPT_SELLER_NAME'),
        'tax_id' => env('RECEIPT_SELLER_TAX_ID'),
        'branch_code' => env('RECEIPT_SELLER_BRANCH'),
        'address' => env('RECEIPT_SELLER_ADDRESS'),
    ],
];
