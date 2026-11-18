<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Privacy\Actions\AnonymizeStagingData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use RuntimeException;

final class AnonymizeStagingCommand extends Command
{
    protected $signature = 'app:anonymize {--force : Run without the confirmation prompt} {--password= : Password to set on every account (default: random)}';

    protected $description = 'Staging refresh: anonymize names, emails, phones, credentials and files in a copied database (YG-GAP-PRV-003). Refuses to run in production.';

    public function handle(AnonymizeStagingData $anonymize): int
    {
        if (app()->environment('production')) {
            $this->error(__('privacy.anonymizer_production'));

            return self::FAILURE;
        }
        if (! app()->environment(Config::array('privacy.anonymizer_environments'))) {
            $this->error(__('privacy.anonymizer_environment'));

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('This rewrites every person in the current database. Continue?')) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $password = $this->option('password');

        try {
            $report = $anonymize(is_string($password) && $password !== '' ? $password : null);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($report as $area => $count) {
            $this->line(sprintf('%s: %d', $area, $count));
        }

        return self::SUCCESS;
    }
}
