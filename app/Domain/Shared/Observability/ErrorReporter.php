<?php

declare(strict_types=1);

namespace App\Domain\Shared\Observability;

use Throwable;

interface ErrorReporter
{
    public function report(Throwable $exception): void;
}
