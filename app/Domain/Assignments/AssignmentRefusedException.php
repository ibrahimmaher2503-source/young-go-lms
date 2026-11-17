<?php

declare(strict_types=1);

namespace App\Domain\Assignments;

use RuntimeException;

/** Why a learner action on an assignment was refused. NO_ACCESS never says whether the assignment exists (the controller answers 404). */
final class AssignmentRefusedException extends RuntimeException
{
    public const string NO_ACCESS = 'no_access';

    public const string NOT_EDITABLE = 'not_editable';

    public const string TYPE_NOT_ALLOWED = 'type_not_allowed';

    public const string TOO_MANY_FILES = 'too_many_files';

    public const string TOO_LARGE = 'too_large';

    public const string NOTHING_TO_SUBMIT = 'nothing_to_submit';

    public const string LATE_NOT_ALLOWED = 'late_not_allowed';

    public const string REVISIONS_EXHAUSTED = 'revisions_exhausted';

    public const string NOT_RETURNED = 'not_returned';

    public const string INVALID_FILE = 'invalid_file';

    public const string TEXT_TOO_LONG = 'text_too_long';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('assignment.refused.'.$reason));
    }
}
