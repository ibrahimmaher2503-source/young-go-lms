<?php

declare(strict_types=1);

namespace App\Domain\Migration\Enums;

enum MigrationExceptionSeverity: string
{
    case Warning = 'warning';
    case Error = 'error';
    case DecisionRequired = 'decision_required';
}
