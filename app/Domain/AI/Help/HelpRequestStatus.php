<?php

declare(strict_types=1);

namespace App\Domain\AI\Help;

/** Help request states (AI spec 12): New, In Progress, Instructor Replied, Waiting for Student, Resolved, Closed. */
enum HelpRequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case InstructorReplied = 'instructor_replied';
    case WaitingForStudent = 'waiting_for_student';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /** @return list<self> */
    public static function open(): array
    {
        return [self::New, self::InProgress, self::InstructorReplied, self::WaitingForStudent];
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::open());
    }

    /** @return list<string> */
    public static function awaitingValues(): array
    {
        return [self::New->value, self::InProgress->value];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /** The next move is the instructor's: the SLA clock runs. */
    public function awaitsInstructor(): bool
    {
        return $this === self::New || $this === self::InProgress;
    }

    /** The learner may reply only after the instructor wrote: no unrestricted chat. */
    public function learnerMayReply(): bool
    {
        return $this === self::InstructorReplied || $this === self::WaitingForStudent;
    }

    public function label(): string
    {
        return __('help.statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::InstructorReplied, self::WaitingForStudent => 'primary',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }
}
