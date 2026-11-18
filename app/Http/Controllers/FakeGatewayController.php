<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\Actions\ProcessPaymentWebhook;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Money\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Demo stand-in for the hosted payment page of the fake gateway: local and testing environments only (404 everywhere else,
 * checked on every call). Pay and Fail post a signed fake callback through ProcessPaymentWebhook, the same path a real
 * gateway callback takes, then send the payer back to the checkout return page.
 */
final class FakeGatewayController
{
    public function show(string $reference): View
    {
        $attempt = $this->attempt($reference);
        $market = Market::tryFrom((string) Order::query()->whereKey($attempt->order_id)->value('market')) ?? Market::Egypt;

        return view('checkout.fake-gateway', [
            'attempt' => $attempt, 'market' => $market, 'money' => Money::ofMinor($attempt->amount_minor, $attempt->currency),
            'orderReference' => (string) Order::query()->whereKey($attempt->order_id)->value('reference'),
        ]);
    }

    public function complete(Request $request, string $reference, ProcessPaymentWebhook $process): RedirectResponse
    {
        $attempt = $this->attempt($reference);
        $data = $request->validate(['outcome' => ['required', 'in:pay,fail']]);

        $payload = [
            'id' => 'fake_txn_'.$attempt->reference.'_'.$data['outcome'], 'reference' => $attempt->reference, 'order_id' => $attempt->provider_order_id,
            'amount_minor' => $attempt->amount_minor, 'currency' => $attempt->currency,
            'success' => $data['outcome'] === 'pay', 'pending' => false, 'refunded' => false, 'voided' => false,
        ];
        $process($payload, FakePaymentGateway::sign($payload));

        return redirect()->route('checkout.return', ['order' => (string) Order::query()->whereKey($attempt->order_id)->value('reference')]);
    }

    private function attempt(string $reference): PaymentAttempt
    {
        abort_unless(app()->environment('local', 'testing'), 404);
        $attempt = PaymentAttempt::query()->where('reference', $reference)->where('gateway', 'fake')->first();
        abort_if($attempt === null || ! $attempt->status->isOpen(), 404);

        return $attempt;
    }
}
