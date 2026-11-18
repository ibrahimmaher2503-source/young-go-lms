<?php

declare(strict_types=1);

namespace App\Domain\Live\Queries;

use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\LearnerCourses;
use App\Domain\Live\JoinWindow;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/** The live sessions a learner may see on the dashboard: current courses only, same access decision as the player, cohort seat when the session has one. */
final class UpcomingSessions
{
    /**
     * @param  Collection<int, Course>|null  $courses
     * @return Collection<int, array{session: LiveSession, item_id: int, course_id: int, course_title: string, state: string}>
     */
    public static function for(User $learner, int $limit = 5, ?Collection $courses = null): Collection
    {
        $courses = ($courses ?? LearnerCourses::for($learner)->where('access', 'active')->pluck('course'))->keyBy('id');
        if ($courses->isEmpty()) {
            return collect();
        }

        $access = app(EntitlementService::class);
        $items = CurriculumItem::query()->whereIn('course_id', $courses->keys())->where('status', CurriculumItem::STATUS_PUBLISHED)
            ->whereHas('section', fn ($section) => $section->where('published', true)->whereNull('archived_at'))
            ->whereHasMorph('itemable', [LiveSession::class], fn ($session) => $session->whereNull('archived_at')
                ->whereNotIn('status', [LiveSession::CANCELLED, LiveSession::COMPLETED])->where('ends_at', '>=', now()))
            ->with(['section', 'itemable' => fn (MorphTo $morph) => $morph->morphWith([LiveSession::class => ['cohort']])])->get();
        $items->each(fn (CurriculumItem $item) => $item->setRelation('course', $courses->get($item->course_id)));
        $access->preloadItemAccess($learner, $items);
        $sequential = CurriculumOutline::coursesFrom($courses->only($items->pluck('course_id')->all())->where('sequence_mode', Course::SEQUENCE_SEQUENTIAL));
        $ordered = CurriculumOutline::forStudentCourses($sequential)->flatMap(fn (CourseSection $section) => $section->items)->groupBy('course_id');
        $rows = collect();

        foreach ($items as $item) {
            $session = $item->itemable;
            if (! $session instanceof LiveSession || $session->archived_at !== null || in_array($session->status, [LiveSession::CANCELLED, LiveSession::COMPLETED], true) || $session->ends_at->isPast()) {
                continue;
            }
            if (! $access->canAccessItem($learner, $item, $ordered->get($item->course_id))->allowed) {
                continue;
            }
            $rows->push([
                'session' => $session, 'item_id' => (int) $item->getKey(), 'course_id' => (int) $item->course_id,
                'course_title' => (string) $item->course->title, 'state' => JoinWindow::state($session),
            ]);
        }

        return $rows->sortBy(fn (array $row): int => $row['session']->starts_at->getTimestamp())->take($limit)->values();
    }
}
