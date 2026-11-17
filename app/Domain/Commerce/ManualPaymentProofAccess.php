<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Models\User;

/** Who may open a proof file or a payer's submit form: the payer who submitted it, or active finance/admin staff. Fails closed. */
final class ManualPaymentProofAccess
{
    public static function allows(User $viewer, ManualPaymentProof $proof): bool
    {
        if ($viewer->getAttribute('status') !== 'active') {
            return false;
        }

        return (int) $proof->payer_id === (int) $viewer->getKey() || self::isStaff($viewer);
    }

    public static function isStaff(User $viewer): bool
    {
        return $viewer->getAttribute('status') === 'active' && ($viewer->can('payment.approve_manual') || $viewer->can('payment.view'));
    }
}
