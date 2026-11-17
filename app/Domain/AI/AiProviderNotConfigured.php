<?php

declare(strict_types=1);

namespace App\Domain\AI;

use RuntimeException;

/** Raised when something asks a model provider before the owner has approved and configured one (O-014). */
final class AiProviderNotConfigured extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No AI provider is configured. Provider and model selection is an open owner decision (O-014).');
    }
}
