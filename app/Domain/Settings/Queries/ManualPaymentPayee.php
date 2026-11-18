<?php

declare(strict_types=1);

namespace App\Domain\Settings\Queries;

use App\Domain\Settings\PaymentsSettings;
use Illuminate\Support\Facades\Config;

/** The payee shown at checkout: the admin-set value first, then the MANUAL_PAYMENT_PAYEE environment value, else null (generic text). */
final class ManualPaymentPayee
{
    public function __construct(private readonly PaymentsSettings $settings) {}

    public function __invoke(): ?string
    {
        foreach ([$this->settings->manual_payee, Config::get('manual_payments.payee')] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
