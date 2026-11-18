<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Demo\DemoAnchors;
use App\Domain\Demo\DemoGuard;
use App\Domain\Demo\DemoRefused;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

final class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed';

    protected $description = 'Create the demo dataset (bilingual courses, people, orders). Local and staging only; refuses in production.';

    public function handle(): int
    {
        try {
            DemoGuard::assertAllowed();
            if (DemoAnchors::exists()) {
                $this->error(__('demo.already_seeded'));

                return self::FAILURE;
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
