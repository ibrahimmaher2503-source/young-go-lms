<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

enum GuardFailure: string
{
    case Schema = 'schema';
    case Length = 'length';
    case Citation = 'citation';
    case Quote = 'quote';
    case Leakage = 'leakage';
}
