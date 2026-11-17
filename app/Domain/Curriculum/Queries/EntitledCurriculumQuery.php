<?php

declare(strict_types=1);

namespace App\Domain\Curriculum\Queries;

use App\Domain\Entitlements\EntitlementService;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;

/**
 * The curriculum as one learner sees it: the published sections and items in the shared order, each marked open,
 * preview or locked, with the reason a locked item is closed. All access answers come from the EntitlementService.
 */
final class EntitledCurriculumQuery
{
    public function __construct(private readonly EntitlementService $access) {}

    /**
     * @return list<array{id: int, title: string, items: list<array{id: int, title: string, type: string, state: string, reason: string|null, unlock_at: string|null, minutes: int}>}>
     */
    public function for(User $learner, Course $course): array
    {
        $entitled = $this->access->currentFor($learner, $course) !== null;
        $sections = [];

        foreach (CurriculumOutline::forStudents($course) as $section) {
            $items = [];
            foreach ($section->items as $item) {
                /** @var CurriculumItem $item */
                $decision = $this->access->canAccessItem($learner, $item);
                $entity = $item->itemable;
                $state = match (true) {
                    ! $decision->allowed => 'locked',
                    ! $entitled && $entity instanceof Lesson && $entity->is_preview => 'preview',
                    default => 'open',
                };
                $items[] = [
                    'id' => (int) $item->getKey(),
                    'title' => (string) ($entity?->getAttribute('title') ?? ''),
                    'type' => $entity instanceof Lesson ? $entity->type : (string) $item->itemable_type,
                    'state' => $state,
                    'reason' => $decision->message(),
                    'unlock_at' => $decision->unlockAt?->toIso8601String(),
                    'minutes' => $entity instanceof Lesson ? (int) round((int) ($entity->media->duration_seconds ?? $entity->duration_seconds ?? 0) / 60) : 0,
                ];
            }
            $sections[] = ['id' => (int) $section->getKey(), 'title' => (string) $section->title, 'items' => $items];
        }

        return $sections;
    }

    /**
     * Previous and next openable items around one item, in curriculum order.
     *
     * @param  list<array{id: int, items: list<array{id: int, state: string}>}>  $sections
     * @return array{previous: int|null, next: int|null}
     */
    public static function neighbours(array $sections, int $itemId): array
    {
        $flat = [];
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $flat[] = $item;
            }
        }

        $position = array_search($itemId, array_column($flat, 'id'), true);
        if ($position === false) {
            return ['previous' => null, 'next' => null];
        }

        $previous = null;
        for ($i = $position - 1; $i >= 0; $i--) {
            if ($flat[$i]['state'] !== 'locked') {
                $previous = $flat[$i]['id'];
                break;
            }
        }
        $next = null;
        for ($i = $position + 1, $count = count($flat); $i < $count; $i++) {
            if ($flat[$i]['state'] !== 'locked') {
                $next = $flat[$i]['id'];
                break;
            }
        }

        return ['previous' => $previous, 'next' => $next];
    }
}
