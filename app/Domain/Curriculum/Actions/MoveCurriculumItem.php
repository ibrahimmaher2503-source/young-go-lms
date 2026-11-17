<?php

declare(strict_types=1);

namespace App\Domain\Curriculum\Actions;

use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Keyboard-friendly alternative to drag and drop: move one item up or down, across sections at the edges. */
final class MoveCurriculumItem
{
    public function __construct(private readonly ReorderCurriculum $reorder) {}

    public function __invoke(User $actor, CurriculumItem $item, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['direction' => __('curriculum.stale')]);
        }

        $course = $item->course;
        $layout = [];
        foreach (CurriculumOutline::forManager($course) as $section) {
            $layout[] = ['section_id' => (int) $section->getKey(), 'items' => $section->items->pluck('id')->map(static fn ($id): int => (int) $id)->all()];
        }

        foreach ($layout as $s => $row) {
            $index = array_search((int) $item->getKey(), $row['items'], true);
            if ($index === false) {
                continue;
            }

            array_splice($layout[$s]['items'], $index, 1);
            if ($direction === 'up') {
                if ($index > 0) {
                    array_splice($layout[$s]['items'], $index - 1, 0, [(int) $item->getKey()]);
                } elseif (isset($layout[$s - 1])) {
                    $layout[$s - 1]['items'][] = (int) $item->getKey();
                } else {
                    array_splice($layout[$s]['items'], 0, 0, [(int) $item->getKey()]);
                }
            } elseif ($index < count($row['items']) - 1) {
                array_splice($layout[$s]['items'], $index + 1, 0, [(int) $item->getKey()]);
            } elseif (isset($layout[$s + 1])) {
                array_unshift($layout[$s + 1]['items'], (int) $item->getKey());
            } else {
                $layout[$s]['items'][] = (int) $item->getKey();
            }

            ($this->reorder)($actor, $course, $layout);

            return;
        }

        throw ValidationException::withMessages(['item' => __('curriculum.stale')]);
    }
}
