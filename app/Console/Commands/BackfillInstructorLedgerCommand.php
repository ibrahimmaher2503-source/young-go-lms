<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Commerce\Order;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundStatus;
use App\Domain\InstructorFinance\Actions\RecordOrderEarnings;
use App\Domain\InstructorFinance\Actions\ReverseEarningsForRefund;
use Illuminate\Console\Command;

final class BackfillInstructorLedgerCommand extends Command
{
    protected $signature = 'ledger:backfill';

    protected $description = 'Write any missing instructor earnings for paid orders and reversals for executed refunds (idempotent).';

    public function handle(RecordOrderEarnings $earnings, ReverseEarningsForRefund $reversals): int
    {
        $entries = 0;
        Order::query()->whereNotNull('paid_at')->orderBy('id')->chunkById(200, static function ($orders) use ($earnings, &$entries): void {
            foreach ($orders as $order) {
                $entries += $earnings($order);
            }
        });

        $reversed = 0;
        Refund::query()->where('status', RefundStatus::Executed->value)->orderBy('id')->chunkById(200, static function ($refunds) use ($reversals, &$reversed): void {
            foreach ($refunds as $refund) {
                $reversed += $reversals($refund);
            }
        });

        $this->info('Wrote '.$entries.' earnings and '.$reversed.' reversals.');

        return self::SUCCESS;
    }
}
