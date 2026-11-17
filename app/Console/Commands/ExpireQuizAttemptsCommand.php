<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Assessment\Actions\ExpireQuizAttempts;
use Illuminate\Console\Command;

final class ExpireQuizAttemptsCommand extends Command
{
    protected $signature = 'quiz:expire-attempts';

    protected $description = 'Submit quiz attempts whose time is over, using the answers saved so far.';

    public function handle(ExpireQuizAttempts $expire): int
    {
        $this->info('Closed '.$expire().' expired quiz attempts.');

        return self::SUCCESS;
    }
}
