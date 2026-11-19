<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Commerce\Actions\ReleaseExpiredSeatHolds;
use Illuminate\Console\Command;

final class ReleaseExpiredSeatHoldsCommand extends Command
{
    protected $signature = 'orders:release-expired-seat-holds';

    protected $description = 'Give back cohort seats held for unpaid orders whose hold time ran out (orders still being paid keep their seat).';

    public function handle(ReleaseExpiredSeatHolds $release): int
    {
        $result = $release();
        $this->info('Released '.$result['released'].' expired seat holds, extended '.$result['extended'].' for orders still being paid.');

        return self::SUCCESS;
    }
}
