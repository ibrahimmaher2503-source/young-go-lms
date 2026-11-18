<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Commerce\Actions\ExpireAbandonedOrders;
use Illuminate\Console\Command;

final class ExpireAbandonedOrdersCommand extends Command
{
    protected $signature = 'orders:expire-abandoned';

    protected $description = 'Cancel pending orders with no open payment or transfer proof so their coupon uses are released.';

    public function handle(ExpireAbandonedOrders $expire): int
    {
        $this->info('Cancelled '.$expire().' abandoned orders.');

        return self::SUCCESS;
    }
}
