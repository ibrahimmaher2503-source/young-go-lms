<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

use LogicException;

/**
 * Tables no retention class may delete from, including financial evidence, access records and the audit log.
 * Pruning code asserts against this list so a wrong class definition or setting cannot reach them.
 */
final class ProtectedData
{
    public const array TABLES = [
        'orders', 'order_items', 'order_item_allocations', 'payment_attempts', 'payment_events', 'payment_reconciliations',
        'payment_reconciliation_mismatches', 'refunds', 'e_receipts', 'manual_payment_proofs', 'coupon_redemptions',
        'instructor_ledger_entries', 'instructor_statements', 'instructor_settlements', 'instructor_settlement_items',
        'instructor_settlement_entries', 'cohort_members', 'cohort_seat_holds',
        'certificates', 'consents', 'activity_log',
    ];

    /** @param  list<string>  $tables */
    public static function assertNotProtected(array $tables): void
    {
        $hit = array_values(array_intersect($tables, self::TABLES));
        if ($hit !== []) {
            throw new LogicException('Protected tables cannot be pruned: '.implode(', ', $hit));
        }
    }
}
