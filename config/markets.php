<?php

declare(strict_types=1);

return [
    'EG' => [
        'active' => true,
        'currency' => 'EGP',
        'timezone' => 'Africa/Cairo',
    ],
    'SA' => [
        'active' => false,
        'currency' => 'SAR',
        'timezone' => 'Asia/Riyadh',
        // O-013: populate these from the approved Saudi profile before T-P12-01 activation.
        'legal_entity_name' => null,
        'tax_registration_number' => null,
        'invoice_settings' => null,
        'gateway' => null,
    ],
];
