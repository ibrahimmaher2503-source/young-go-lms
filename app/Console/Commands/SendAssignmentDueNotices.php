<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Assignments\Actions\SendAssignmentNotices;
use Illuminate\Console\Command;

final class SendAssignmentDueNotices extends Command
{
    protected $signature = 'assignments:send-due-notices';

    protected $description = 'Send reminders for assignments due in 7, 3 or 1 day.';

    public function handle(SendAssignmentNotices $notices): int
    {
        $this->info('Sent '.$notices->due().' assignment reminders.');

        return self::SUCCESS;
    }
}
