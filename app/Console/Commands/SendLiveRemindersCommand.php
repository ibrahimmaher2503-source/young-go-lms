<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Live\Actions\SendLiveReminders;
use Illuminate\Console\Command;

final class SendLiveRemindersCommand extends Command
{
    protected $signature = 'live:send-reminders';

    protected $description = 'Send the live session reminders that are due (each person once per reminder).';

    public function handle(SendLiveReminders $reminders): int
    {
        $this->info('Sent '.$reminders().' live session reminders.');

        return self::SUCCESS;
    }
}
