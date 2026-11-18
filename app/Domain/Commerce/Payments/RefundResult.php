<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

/** A refund the provider accepted. */
final readonly class RefundResult
{
    public function __construct(public string $gateway, public string $providerRefundId) {}
}
