<?php

declare(strict_types=1);

namespace App\Domain\AI\Guards;

/** AI spec 8.1: abuse, sexual content, self-harm, violence. */
enum ModerationCategory: string
{
    case Abuse = 'abuse';
    case Sexual = 'sexual';
    case SelfHarm = 'self_harm';
    case Violence = 'violence';
}
