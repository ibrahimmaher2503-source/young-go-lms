<?php

declare(strict_types=1);

namespace App\Domain\AI\Help;

/** Shared check of a thread reply: not empty, not longer than ai.help.reply_max_chars. */
final class ReplyBody
{
    public static function clean(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            throw new HelpRefusedException(HelpRefusedException::BODY_NEEDED);
        }
        if (mb_strlen($body) > (int) config('ai.help.reply_max_chars', 2000)) {
            throw new HelpRefusedException(HelpRefusedException::BODY_TOO_LONG);
        }

        return $body;
    }
}
