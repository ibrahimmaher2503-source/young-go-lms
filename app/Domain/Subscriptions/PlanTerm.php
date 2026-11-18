<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** How long one paid period lasts (D-008: monthly or annual). Month ends are not overflowed (31 Jan + 1 month = 28/29 Feb). */
enum PlanTerm: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';

    public function endFrom(CarbonInterface $start): CarbonImmutable
    {
        $start = CarbonImmutable::instance($start);

        return $this === self::Monthly ? $start->addMonthNoOverflow() : $start->addYearNoOverflow();
    }
}
