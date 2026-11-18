<?php

declare(strict_types=1);

return [
    // Private disk that holds protected files (never the public disk).
    'disk' => 'local',

    // Signed playback links for protected (T1) files expire after this many seconds.
    'signed_ttl' => 300,

    // Embeddable hosts the Allowlisted iframe provider accepts. Empty means none: the owner approves each host.
    'iframe_allowlist' => [],

    // Applied to every iframe embed; widen only with a recorded reason.
    'iframe_sandbox' => 'allow-scripts allow-same-origin allow-presentation',

    // Upload ceiling for private files, in kilobytes (used by T-P3-06).
    'max_kilobytes' => 2097152,

    // Lesson materials (PDF, text, images) in kilobytes.
    'resource_max_kilobytes' => 51200,

    // Curriculum PDF limits (AI spec 6.1 defaults): 25 MB and 120 pages.
    'pdf_max_kilobytes' => 25600,
    'pdf_max_pages' => 120,
    'qpdf_binary' => env('MEDIA_QPDF_BINARY'),
];
