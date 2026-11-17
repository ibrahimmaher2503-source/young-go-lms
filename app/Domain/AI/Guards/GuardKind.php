<?php

declare(strict_types=1);

namespace App\Domain\AI\Guards;

/** Which input guard stopped a question before any provider call. The value is stored in guard_flags and ai_usage.result. */
enum GuardKind: string
{
    case Moderation = 'moderation';
    case Safeguarding = 'safeguarding';
    case Injection = 'injection';
    case QuizGuard = 'quiz_guard';
}
