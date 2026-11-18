<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

/** What differs between our records and the gateway (T-P8-11). Every type is report-only; none changes an order. */
enum ReconciliationMismatchType: string
{
    /** The gateway has a transaction whose reference matches no attempt of ours. */
    case UnmatchedTransaction = 'unmatched_transaction';
    /** Amount or currency at the gateway differs from our attempt. */
    case AmountMismatch = 'amount_mismatch';
    /** The gateway and our attempt/order disagree on whether it was paid. */
    case StatusMismatch = 'status_mismatch';
    /** We recorded a successful payment in the window that the gateway listing does not show. */
    case MissingAtGateway = 'missing_at_gateway';
    /** The gateway refunded or voided a transaction; refunds are decided by finance. */
    case RefundedAtGateway = 'refunded_at_gateway';
}
