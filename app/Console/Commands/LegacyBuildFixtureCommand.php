<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Support\LegacyFixtureBuilder;
use Illuminate\Console\Command;
use Throwable;

final class LegacyBuildFixtureCommand extends Command
{
    protected $signature = 'legacy:build-fixture
        {--path= : Custom SQLite file path for the synthetic legacy fixture}
        {--force : Overwrite an existing fixture SQLite file}';

    protected $description = 'Build the synthetic SQLite dataset shaped like the legacy schema in docs/migration/INVENTORY.md';

    public function handle(LegacyFixtureBuilder $builder): int
    {
        $custom = $this->option('path');
        $path = is_string($custom) && $custom !== ''
            ? $custom
            : (string) config('legacy_migration.fixture_path', database_path('fixtures/legacy/synthetic_legacy.sqlite'));

        try {
            $written = $builder->build($path, (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Synthetic legacy fixture created at: {$written}");

        return self::SUCCESS;
    }
}
