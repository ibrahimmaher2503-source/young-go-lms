<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AI\Help\Actions\EscalateOverdueHelpRequests;
use Illuminate\Console\Command;

final class EscalateOverdueHelpRequestsCommand extends Command
{
    protected $signature = 'help:escalate-overdue';

    protected $description = 'Mark help requests that waited too long for an instructor as overdue and notify the admins.';

    public function handle(EscalateOverdueHelpRequests $escalate): int
    {
        $this->info('Escalated '.$escalate().' overdue help requests.');

        return self::SUCCESS;
    }
}
