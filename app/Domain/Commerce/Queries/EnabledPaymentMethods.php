<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Commerce\Contracts\PaymentGateway;

/** The checkout methods a payer may pick: the configured list, in config order, narrowed to what the active gateway serves (A2). */
final class EnabledPaymentMethods
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /** @return list<array{key: string, label: string}> */
    public function __invoke(): array
    {
        $enabled = $this->gateway->supportedMethods();
        $methods = [];
        foreach ((array) config('payments.methods', []) as $key) {
            $key = (string) $key;
            if (in_array($key, $enabled, true)) {
                $methods[] = ['key' => $key, 'label' => __('payments.method_'.$key)];
            }
        }

        return $methods;
    }
}
