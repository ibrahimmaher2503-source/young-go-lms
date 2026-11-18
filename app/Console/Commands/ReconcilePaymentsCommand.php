<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Commerce\Actions\ReconcilePayments;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\Payments\PaymentGatewayException;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile {--date= : Day to reconcile (YYYY-MM-DD), default yesterday}';

    protected $description = 'Compare payment attempts and orders with the gateway transaction listing for one day and record mismatches (T-P8-11). Report only.';

    public function handle(ReconcilePayments $reconcile, PaymentGateway $gateway): int
    {
        $option = $this->option('date');
        try {
            $day = is_string($option) && $option !== '' ? CarbonImmutable::parse($option) : now()->toImmutable()->subDay();
        } catch (Throwable) {
            $this->error('The date must look like 2026-11-18.');

            return self::INVALID;
        }

        if (! $gateway->isConfigured()) {
            $this->info('Payment gateway is not configured; reconciliation skipped.');

            return self::SUCCESS;
        }

        try {
            $run = $reconcile($day);
        } catch (PaymentGatewayException $e) {
            $this->error('Reconciliation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Reconciled %s (%s): %d transactions, %d matched, %d mismatches.',
            $run->run_date->toDateString(), $run->gateway, $run->transactions_seen, $run->matched, $run->mismatched,
        ));

        return self::SUCCESS;
    }
}
