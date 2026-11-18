<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Subscriptions\Actions\ExpireSubscriptions;
use App\Domain\Subscriptions\Actions\SendSubscriptionRenewalReminders;
use Illuminate\Console\Command;

final class ProcessSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:process';

    protected $description = 'Send due subscription renewal reminders (each once) and expire subscriptions past their grace period.';

    public function handle(SendSubscriptionRenewalReminders $reminders, ExpireSubscriptions $expire): int
    {
        $this->info('Sent '.$reminders().' renewal reminders.');
        $this->info('Expired '.$expire().' subscriptions.');

        return self::SUCCESS;
    }
}
