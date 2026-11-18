<?php

declare(strict_types=1);

return [
    // Largest proof file (kilobytes). Working default, 5 MB.
    'max_kilobytes' => 5120,

    // Content types a proof may have. The file content decides, never the client name or type.
    'mimes' => [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ],

    // Finance review target in hours; a pending proof past it shows as overdue. Working default (PRD 29.15 gives no figure).
    'sla_hours' => 24,

    // A transfer older than this many days is refused (working default).
    'max_age_days' => 30,

    // Where the payer sends the transfer (Instapay handle, bank details). UNKNOWN until the owner supplies it (AGENTS rule 2); empty shows generic text.
    'payee' => env('MANUAL_PAYMENT_PAYEE'),

    // Where proofs live inside the private media disk.
    'directory' => 'manual-payments',
];
