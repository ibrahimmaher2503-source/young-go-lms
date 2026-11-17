<?php

declare(strict_types=1);

namespace App\Domain\Cohorts;

use RuntimeException;

/** Thrown when a seat is asked for in a cohort that has none left. Callers (checkout, grants) offer the waitlist. */
final class CohortFullException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('cohorts.full'));
    }
}
