<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Entitlements\Actions\SendAccessExpiryNotices as SendNotices;
use Illuminate\Console\Command;

final class SendAccessExpiryNotices extends Command
{
    protected $signature = 'entitlements:send-expiry-notices';

    protected $description = 'Send due course access expiry notices.';

    public function handle(SendNotices $send): int
    {
        $this->info('Sent '.$send().' access expiry notices.');

        return self::SUCCESS;
    }
}
