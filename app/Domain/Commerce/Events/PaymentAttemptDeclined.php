<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\PaymentAttempt;

final readonly class PaymentAttemptDeclined
{
    public function __construct(public PaymentAttempt $attempt) {}
}
