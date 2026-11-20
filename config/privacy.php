<?php

declare(strict_types=1);

/*
| T-P9-08 privacy rights. Retention periods are OPEN (O-025): the defaults live in App\Domain\Privacy\RetentionClass and admins
| can override them per class; financial, certificate and audit data is never pruned by any setting.
*/
return [
    // Smallest retention an admin may save (days); the export archive class is exempt (it may be as short as one day).
    'min_retention_days' => 30,

    // Private disk folder for data-export archives (the disk comes from config('media.disk')).
    'export_directory' => 'privacy/exports',

    // Environments where `app:anonymize` may run. Production is refused regardless of this list or any flag.
    'anonymizer_environments' => ['local', 'staging', 'testing'],

    // Only accounts holding no role outside this list can be anonymized through a deletion request.
    'self_service_roles' => ['student', 'parent'],

    // Where staging placeholder files point (created by `app:anonymize`).
    'placeholder_file' => 'privacy/staging-placeholder.txt',

    // Grace window (minutes) before unreferenced uploads in privacy-sensitive folders are treated as orphaned.
    'orphan_grace_minutes' => 60,
];
