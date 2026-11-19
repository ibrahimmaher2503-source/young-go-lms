<?php

declare(strict_types=1);

return [
    'connection' => env('LEGACY_MIGRATION_CONNECTION', 'legacy'),
    'source_system' => env('LEGACY_SOURCE_SYSTEM', 'legacy_academy'),
    'default_chunk_size' => (int) env('LEGACY_MIGRATION_CHUNK_SIZE', 100),
    'default_market' => env('LEGACY_DEFAULT_MARKET', 'EG'),
    'default_currency' => env('LEGACY_DEFAULT_CURRENCY', 'EGP'),
    'default_locale' => env('LEGACY_DEFAULT_LOCALE', 'ar'),
    'default_timezone' => env('LEGACY_DEFAULT_TIMEZONE', 'Africa/Cairo'),
    'storage_root' => env('LEGACY_STORAGE_ROOT'),
    'fixture_path' => env('LEGACY_FIXTURE_PATH', database_path('fixtures/legacy/synthetic_legacy.sqlite')),
    'sample_size' => (int) env('LEGACY_VERIFY_SAMPLE_SIZE', 5),
    'enforce_read_only' => true,
    'demo_mobile_video_url' => 'https://www.html5rocks.com/en/tutorials/video/basics/devstories.webm',
];
