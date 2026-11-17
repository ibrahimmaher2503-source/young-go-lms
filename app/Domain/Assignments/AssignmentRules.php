<?php

declare(strict_types=1);

namespace App\Domain\Assignments;

use App\Models\Assignment;
use Illuminate\Support\Facades\Config;

/** What an assignment allows learners to hand in, read from its configuration and the platform ceilings. */
final class AssignmentRules
{
    /** The per-file limit in kilobytes that applies to learners of this assignment. */
    public static function limitKilobytes(Assignment $assignment): int
    {
        return min($assignment->max_size_kb ?? Config::integer('assignments.max_kilobytes'), Config::integer('assignments.ceiling_kilobytes'));
    }

    /** @return list<string> the file kinds learners may upload (text is a written answer, not a file) */
    public static function fileKinds(Assignment $assignment): array
    {
        return array_values(array_diff($assignment->allowed_types ?? [], ['text']));
    }

    public static function allowsText(Assignment $assignment): bool
    {
        return in_array('text', $assignment->allowed_types ?? [], true);
    }

    /** @return list<string> accepted content types for the file input, from the allowed kinds */
    public static function acceptedMimes(Assignment $assignment): array
    {
        $mimes = [];
        foreach (self::fileKinds($assignment) as $kind) {
            foreach ((array) Config::get('assignments.kinds.'.$kind, []) as $mime) {
                $mimes[] = (string) $mime;
            }
        }

        return $mimes;
    }
}
