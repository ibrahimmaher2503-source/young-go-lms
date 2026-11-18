<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/** Manual (Instapay/bank) payment payee details shown at checkout (T-P9-09). Null falls back to the MANUAL_PAYMENT_PAYEE environment value. Never audited by value. */
final class PaymentsSettings extends Settings
{
    public ?string $manual_payee;

    public static function group(): string
    {
        return 'payments';
    }
}
