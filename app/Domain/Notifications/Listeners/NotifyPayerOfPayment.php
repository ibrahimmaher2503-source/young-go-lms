<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Commerce\Events\ManualPaymentApproved;
use App\Domain\Commerce\Events\ManualPaymentProofSubmitted;
use App\Domain\Commerce\Events\ManualPaymentRejected;
use App\Domain\Commerce\Events\PaymentAttemptDeclined;
use App\Domain\Commerce\Events\PaymentCheckoutStarted;
use App\Domain\Commerce\Events\PaymentVerified;
use App\Domain\Commerce\Events\RefundExecuted;
use App\Domain\Commerce\Order;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Models\User;
use Throwable;

/**
 * T-P9-05: sends payment notices to the payer after the state change commits.
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

    public function checkoutStarted(PaymentCheckoutStarted $event): void
    {
        $order = $event->attempt->order;
        $this->send($order->payer, new TemplatedNotice('payment.pending', ['order' => $order->reference], 'payment-pending:'.$order->getKey(), route('checkout.order', $order)));
    }

    public function declined(PaymentAttemptDeclined $event): void
    {
        $order = $event->attempt->order;
        $this->send($order->payer, new TemplatedNotice('payment.failed', ['order' => $order->reference], 'payment-failed:'.$event->attempt->getKey(), route('checkout.order', $order)));
    }

    public function proofRejected(ManualPaymentRejected $event): void
    {
        $order = $event->proof->order;
        $this->send($order->payer, new TemplatedNotice('payment.review_rejected', ['order' => $order->reference], 'payment-review-rejected:'.$event->proof->getKey(), route('manual-payments.create', $order)));
    }

    public function refunded(RefundExecuted $event): void
    {
        $order = $event->refund->order;
        $this->send($order->payer, new TemplatedNotice('payment.refunded', ['order' => $order->reference], 'payment-refunded:'.$event->refund->getKey(), route('payments.history')));
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
