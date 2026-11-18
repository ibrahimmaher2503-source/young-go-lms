<?php

declare(strict_types=1);

return [
    'EG' => [
        'active' => true,
        'currency' => 'EGP',
        'timezone' => 'Africa/Cairo',
        'gateway' => 'paymob',
    ],
    'SA' => [
        'active' => false,
        'currency' => 'SAR',
        'timezone' => 'Asia/Riyadh',
        // O-013 (decided 2026-11-18): the Saudi gateway is Paymob KSA. The market stays disabled; the legal entity, tax and invoice
        // facts are still to be supplied by the owner before T-P12-01 activation.
        'legal_entity_name' => null,
        'tax_registration_number' => null,
        'invoice_settings' => null,
        'gateway' => 'paymob',
    ],
];
