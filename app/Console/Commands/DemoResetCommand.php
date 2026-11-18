<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Demo\Actions\ResetDemoData;
use App\Domain\Demo\DemoAnchors;
use App\Domain\Demo\DemoGuard;
use App\Domain\Demo\DemoRefused;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

final class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset {--remove-only : Remove the demo data and do not create it again}';

    protected $description = 'Remove only the demo-flagged rows, then rebuild the demo dataset. Local and staging only; refuses in production.';

    public function handle(ResetDemoData $reset): int
    {
        try {
            DemoGuard::assertAllowed();
            $removed = $reset();
            $this->line($removed === 0 ? __('demo.nothing_to_remove') : __('demo.removed', ['rows' => $removed]));

            if ($this->option('remove-only')) {
                return self::SUCCESS;
            }

            $seeder = new DemoSeeder;
            $seeder->setContainer(app())->setCommand($this);
            $seeder->run();
        } catch (DemoRefused $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->info(__('demo.seeded', ['users' => $seeder->userCount, 'courses' => count(DemoAnchors::ids('courses'))]));

        return self::SUCCESS;
    }
}
