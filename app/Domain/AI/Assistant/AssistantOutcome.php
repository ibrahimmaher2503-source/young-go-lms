<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

/** What the learner is told. The value is stored on the message and maps to the UI states (AI spec 9.2, 7.14). */
enum AssistantOutcome: string
{
    case Answered = 'answered';
    case NotInSource = 'not_in_source';
    case OutOfScope = 'out_of_scope';
    /** Provider error, timeout or an answer the output guard could not accept: a safe message, never model text. */
    case Fallback = 'fallback';
}
