<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

enum AssistantMode: string
{
    case Normal = 'normal';
    case Simpler = 'simpler';
}
