<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Queries;

use App\Domain\Instructors\ApplicationStatus;
use App\Domain\Instructors\Models\InstructorApplication;

/** Counts for the instructor application review header. */
final class ApplicationQueue
{
    /** @return array{pending: int, approved: int, rejected: int} */
    public static function summary(): array
    {
        $counts = InstructorApplication::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'pending' => (int) ($counts[ApplicationStatus::Pending->value] ?? 0),
            'approved' => (int) ($counts[ApplicationStatus::Approved->value] ?? 0),
            'rejected' => (int) ($counts[ApplicationStatus::Rejected->value] ?? 0),
        ];
    }
}
