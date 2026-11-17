<?php

declare(strict_types=1);

namespace App\Domain\AI\Resilience;

use RuntimeException;

/** Raised without calling any provider while every route is cooling off after consecutive failures (AI spec 11, outage state). */
final class AiProviderCircuitOpen extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The AI provider circuit is open: no provider is reachable right now.');
    }
}
