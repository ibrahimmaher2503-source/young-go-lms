<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Entitlements\Actions\ExpireEntitlements;
use Illuminate\Console\Command;

final class ExpireEntitlementsCommand extends Command
{
    protected $signature = 'entitlements:expire';

    protected $description = 'Mark entitlements past their end time as expired';

    public function handle(ExpireEntitlements $expire): int
    {
        $this->info('Expired '.$expire().' entitlement(s).');

        return self::SUCCESS;
    }
}
