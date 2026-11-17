<?php

declare(strict_types=1);

namespace App\Domain\AI\Help;

use RuntimeException;

/** Why a feedback or help request change was refused. Messages are in help.refused and are safe to show. */
final class HelpRefusedException extends RuntimeException
{
    public const string NOT_FOUND = 'not_found';

    public const string INVALID_TYPE = 'invalid_type';

    public const string NOT_ESCALATABLE = 'not_escalatable';

    public const string OPEN_LIMIT = 'open_limit';

    public const string BODY_NEEDED = 'body_needed';

    public const string BODY_TOO_LONG = 'body_too_long';

    public const string CLOSED = 'closed';

    public const string NOT_YOUR_TURN = 'not_your_turn';

    public const string BAD_TRANSITION = 'bad_transition';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('help.refused.'.$reason, [
            'max' => (int) config('ai.help.max_open_per_lesson', 3),
            'chars' => (int) config('ai.help.reply_max_chars', 2000),
        ]));
    }
}
