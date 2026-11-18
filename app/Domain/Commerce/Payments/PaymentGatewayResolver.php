<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

use App\Domain\Commerce\Contracts\PaymentGateway;

/**
 * Picks the gateway for this environment. Complete Paymob credentials (and the live gate for live keys) give the Paymob
 * adapter; with none, local and testing get the Fake and every other environment gets the refusing Null gateway (B-07).
 */
final class PaymentGatewayResolver
{
    public function __invoke(): PaymentGateway
    {
        $driver = (string) config('payments.driver', 'auto');
        $sandbox = app()->environment('local', 'testing');

        if ($driver === 'null') {
            return new NullPaymentGateway;
        }
        if ($driver === 'fake') {
            return $sandbox ? new FakePaymentGateway : new NullPaymentGateway;
        }

        $paymob = new PaymobGateway(PaymobCredentials::fromConfig());
        if ($paymob->isConfigured()) {
            return $paymob;
        }

        return $sandbox ? new FakePaymentGateway : new NullPaymentGateway;
    }
}
