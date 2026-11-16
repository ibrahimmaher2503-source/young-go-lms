<?php

declare(strict_types=1);

namespace App\Domain\Shared\Observability;

use Illuminate\Support\Facades\Log;
use Throwable;

final class LogErrorReporter implements ErrorReporter
{
    public function report(Throwable $exception): void
    {
        Log::channel('single')->error('Application exception', [
            'exception_type' => $exception::class,
            'source' => basename($exception->getFile()),
            'line' => $exception->getLine(),
        ]);
    }
}
