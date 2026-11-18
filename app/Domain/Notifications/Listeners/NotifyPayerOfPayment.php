<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Commerce\Events\ManualPaymentApproved;
use App\Domain\Commerce\Events\ManualPaymentProofSubmitted;
use App\Domain\Commerce\Events\PaymentVerified;
use App\Domain\Commerce\Order;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Models\User;
use Throwable;

/**
 * T-P8-03 / T-P8-04 hand-off: tells the payer that a payment was received for review or approved. Never breaks the payment:
 * the money and access are already committed, so a failure is reported. One notice per order (approved) or proof (review).
 */
final class NotifyPayerOfPayment
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function verified(PaymentVerified $event): void
    {
        $this->approved($event->order);
    }

    public function manualApproved(ManualPaymentApproved $event): void
    {
        $this->approved($event->proof->order);
    }

    public function proofSubmitted(ManualPaymentProofSubmitted $event): void
    {
        $this->send($event->proof->payer, new TemplatedNotice('payment.review', ['order' => $event->proof->order->reference], 'payment-review:'.$event->proof->getKey(), route('learner.courses')));
    }

    private function approved(Order $order): void
    {
        $this->send($order->payer, new TemplatedNotice('payment.approved', ['order' => $order->reference], 'payment-approved:'.$order->getKey(), route('learner.courses')));
    }

    private function send(User $payer, TemplatedNotice $notice): void
    {
        try {
            $this->dispatcher->send($payer, $notice);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
