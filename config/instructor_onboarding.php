<?php

declare(strict_types=1);

return [
    // Largest ID document (kilobytes). Working default, 5 MB.
    'id_document_max_kilobytes' => 5120,

    // Content types an ID document may have. The file content decides, never the client name or type.
    'id_document_mimes' => [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ],

    // Where ID documents live inside the private media disk. Never publicly served.
    'id_document_directory' => 'instructor-applications',

    // Most portfolio/sample links an application may carry (working default).
    'max_portfolio_links' => 5,

    // Version key of the placeholder agreement seeded for local use. The wording is a draft pending owner/counsel approval (OPEN).
    'placeholder_agreement_version' => 'instructor-agreement-v1-draft',
];
