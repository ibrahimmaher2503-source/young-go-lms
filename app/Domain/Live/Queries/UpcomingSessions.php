<?php

declare(strict_types=1);

namespace App\Domain\Live\Queries;

use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\LearnerCourses;
use App\Domain\Live\JoinWindow;
use App\Models\CurriculumItem;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Collection;

/** The live sessions a learner may see on the dashboard: current courses only, same access decision as the player, cohort seat when the session has one. */
final class UpcomingSessions
{
    /**
     * @return Collection<int, array{session: LiveSession, item_id: int, course_id: int, course_title: string, state: string}>
     */
    public static function for(User $learner, int $limit = 5): Collection
    {
        $courses = LearnerCourses::for($learner)->filter(fn (array $row): bool => $row['access'] === 'active')->map(fn (array $row) => $row['course']);
        if ($courses->isEmpty()) {
            return collect();
        }

        $access = app(EntitlementService::class);
        $items = CurriculumItem::query()->whereIn('course_id', $courses->pluck('id'))->where('itemable_type', 'live_session')->where('status', CurriculumItem::STATUS_PUBLISHED)->with(['itemable', 'section'])->get();
        $rows = collect();

        foreach ($items as $item) {
            $session = $item->itemable;
            if (! $session instanceof LiveSession || $session->archived_at !== null || in_array($session->status, [LiveSession::CANCELLED, LiveSession::COMPLETED], true) || $session->ends_at->isPast()) {
                continue;
            }
            if (! $item->section?->published || ($session->cohort_id !== null && ! ($session->cohort?->hasSeat($learner) ?? false)) || ! $access->canAccessItem($learner, $item)->allowed) {
                continue;
            }
            $rows->push([
                'session' => $session, 'item_id' => (int) $item->getKey(), 'course_id' => (int) $item->course_id,
                'course_title' => (string) $courses->firstWhere('id', $item->course_id)?->title, 'state' => JoinWindow::state($session),
            ]);
        }

        return $rows->sortBy(fn (array $row): int => $row['session']->starts_at->getTimestamp())->take($limit)->values();
    }
}
