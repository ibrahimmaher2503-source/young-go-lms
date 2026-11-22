<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Catalog\Contracts\CourseHistoryChecker;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

/**
 * Rows in other modules that point at a course with a restricting foreign key (bundle items, plan courses, commission
 * rules, live sessions, help requests, AI conversations, ledger entries). Any of them means the course is referenced and
 * must be archived; without this check the delete fails on the database constraint instead of explaining why.
 */
final class CourseReferenceHistory implements CourseHistoryChecker
{
    /** @var array<string, string> table => reason code (courses.blocked.*) */
    private const array REFERENCES = [
        'bundle_items' => 'bundles',
        'subscription_plan_courses' => 'plans',
        'course_commission_rules' => 'commission_rules',
        'instructor_ledger_entries' => 'ledger',
        'live_sessions' => 'live_sessions',
        'help_requests' => 'help_requests',
        'ai_conversations' => 'ai_conversations',
    ];

    public function blocks(Course $course): ?string
    {
        foreach (self::REFERENCES as $table => $reason) {
            if (DB::table($table)->where('course_id', $course->getKey())->exists()) {
                return $reason;
            }
        }

        return null;
    }
}
