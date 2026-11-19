<?php

declare(strict_types=1);

return [
    'mode' => [
        'dry_run_banner' => 'Legacy migration DRY-RUN completed (no target or tracking tables were modified).',
        'apply_banner' => 'Legacy migration APPLY completed.',
    ],
    'errors' => [
        'mutually_exclusive_mode' => 'Options --dry-run and --apply cannot be combined.',
    ],
    'status' => [
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'dry_run' => 'Dry run',
    ],
];
