<?php

declare(strict_types=1);

namespace App\Domain\Live;

use RuntimeException;

/** Why a join was refused. NO_ACCESS never says whether the session exists; the others are shown to someone who is allowed to see it. */
final class JoinRefusedException extends RuntimeException
{
    public const string NO_ACCESS = 'no_access';

    public const string NOT_OPEN = 'not_open';

    public const string NO_LINK = 'no_link';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
