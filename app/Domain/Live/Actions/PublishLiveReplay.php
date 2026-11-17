<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Live\LiveAccess;
use App\Models\CurriculumItem;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Creates the replay of a finished session as an ordinary recorded video lesson in the same section, linked to the
 * session. It starts as a draft with no media, so it follows every recorded-lesson rule: media before publishing, the
 * normal course entitlement for access, and the assistant only after the lesson has its own approved PDF.
 */
final class PublishLiveReplay
{
    public function __construct(private readonly AuditLogger $audit, private readonly ManageLessons $lessons) {}

    public function __invoke(User $actor, LiveSession $session): CurriculumItem
    {
        LiveAccess::authorize($actor, $session->course);

        return DB::transaction(function () use ($actor, $session): CurriculumItem {
            $locked = LiveSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== LiveSession::COMPLETED) {
                throw ValidationException::withMessages(['status' => __('live.replay.needs_finished')]);
            }
            if ($locked->replay_item_id !== null) {
                throw ValidationException::withMessages(['replay' => __('live.replay.exists')]);
            }
            $liveItem = $locked->item()->firstOrFail();
            $section = $liveItem->section;

            $title = [];
            foreach (['ar', 'en'] as $locale) {
                $source = (string) $locked->getTranslation('title', $locale, false);
                if ($source !== '') {
                    $title[$locale] = __('live.replay.title', ['title' => $source], $locale);
                }
            }

            $item = $this->lessons->add($actor, $section, ['title' => $title, 'type' => 'video']);
            $locked->update(['replay_item_id' => $item->getKey()]);

            if ($this->audit->record('live_session.replay_created', $locked, (int) $actor->getKey(), null, ['item_id' => $item->getKey()]) === null) {
                throw new RuntimeException('Unable to record live session audit.');
            }

            return $item;
        });
    }
}
