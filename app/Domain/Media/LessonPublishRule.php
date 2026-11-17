<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\MediaAsset;
use Illuminate\Validation\ValidationException;

/**
 * YG-GAP-VID-002: paid lessons use protected (T1) or restricted (T2) media. A T3 source is allowed for free courses,
 * free-preview lessons, or when an admin recorded an exception reason on the media.
 */
final class LessonPublishRule
{
    /**
     * @param  iterable<CurriculumItem>  $items
     * @return list<int> ids of items that may not be published yet
     */
    public static function blocked(iterable $items): array
    {
        $blocked = [];
        foreach ($items as $item) {
            $lesson = $item->itemable;
            if (! $lesson instanceof Lesson || $lesson->is_preview) {
                continue;
            }

            $item->loadMissing('course');
            if ($item->course->access_mode !== 'paid') {
                continue;
            }

            $media = $lesson->media;
            if ($media !== null && $media->protection_tier === MediaAsset::TIER_OPEN && ! $media->hasException()) {
                $blocked[] = (int) $item->getKey();
            }
        }

        return $blocked;
    }

    /**
     * @param  iterable<CurriculumItem>  $items
     *
     * @throws ValidationException
     */
    public static function assertPublishable(iterable $items): void
    {
        $blocked = self::blocked($items);
        if ($blocked !== []) {
            throw ValidationException::withMessages(['items' => __('media.t3_paid_blocked', ['count' => count($blocked)])]);
        }
    }
}
