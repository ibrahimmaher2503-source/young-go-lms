<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Listeners;

use App\Domain\Commerce\Events\ManualPaymentApproved;
use App\Domain\Commerce\Events\PaymentVerified;
use App\Domain\Commerce\Events\RefundExecuted;
use App\Domain\Commerce\Order;
use App\Domain\InstructorFinance\Actions\RecordOrderEarnings;
use App\Domain\InstructorFinance\Actions\ReverseEarningsForRefund;
use Throwable;

/**
 * T-P8-09 hooks: writes instructor earnings when an order is paid (online verification or manual approval) and reversals when a
 * refund is executed. Never breaks the payment or refund (the money is already committed): a failure is reported, and the actions
 * are idempotent, so `ledger:backfill` repairs any gap.
 */
final class AccrueInstructorEarnings
{
    public function __construct(private readonly RecordOrderEarnings $earnings, private readonly ReverseEarningsForRefund $reversals) {}

    public function verified(PaymentVerified $event): void
    {
        $this->earn($event->order);
    }

    public function manualApproved(ManualPaymentApproved $event): void
    {
        $order = Order::query()->find($event->proof->order_id);
        if ($order !== null) {
            $this->earn($order);
        }
    }

    public function refunded(RefundExecuted $event): void
    {
        try {
            ($this->reversals)($event->refund);
        } catch (Throwable $failure) {
            report($failure);
        }
    }

    private function earn(Order $order): void
    {
        try {
            ($this->earnings)($order);
        } catch (Throwable $failure) {
            report($failure);
        }
    }
}
