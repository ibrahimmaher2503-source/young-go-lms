<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

/**
 * What a gateway may know about one checkout. Built only by `StartCheckout` from the locked server order.
 */
final readonly class CheckoutIntent
{
    /**
     * @param  list<array{name: string, amount_minor: int, quantity: int}>  $items
     * @param  list<string>  $methods
     */
    public function __construct(
        public string $reference,
        public string $orderReference,
        public int $amountMinor,
        public string $currency,
        public array $items,
        public array $methods,
        public string $customerName,
        public string $customerEmail,
        public string $notificationUrl,
        public string $returnUrl,
    ) {}
}
