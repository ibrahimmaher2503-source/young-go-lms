<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

enum EntitlementSource: string
{
    case Free = 'free';
    case Purchase = 'purchase';
    case Bundle = 'bundle';
    case Subscription = 'subscription';
    case ManualGrant = 'manual_grant';
    case ManualPayment = 'manual_payment';
    case LegacyMigration = 'legacy_migration';

    /** Sources a person hands out, so the actor must hold the grant permission. */
    public function isManual(): bool
    {
        return $this === self::ManualGrant || $this === self::ManualPayment;
    }
}
