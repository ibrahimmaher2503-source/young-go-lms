<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\ActingLearner;
use App\Domain\Live\Actions\GetJoinLink;
use App\Domain\Live\Actions\RecordJoin;
use App\Domain\Live\JoinRefusedException;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Sends an allowed learner to the meeting. POST only, so a link preview or prefetch never triggers it; the URL is never in the page. */
final class LiveJoinController
{
    public function __construct(private readonly ActingLearner $acting, private readonly GetJoinLink $join, private readonly RecordJoin $record) {}

    public function __invoke(Request $request, Course $course, CurriculumItem $item): RedirectResponse
    {
        abort_unless((int) $item->course_id === (int) $course->getKey(), 404);
        $learner = $this->acting->current();
        abort_unless($learner instanceof User, 401);

        try {
            $url = $this->join->__invoke($learner, $item);
        } catch (JoinRefusedException $exception) {
            abort_if($exception->reason === JoinRefusedException::NO_ACCESS, 404);

            return redirect()->route('learn.show', ['course' => $course->getKey(), 'item' => $item->getKey()])->with('join_error', $exception->getMessage());
        }

        $session = $item->itemable;
        if ($session instanceof LiveSession) {
            $this->record->__invoke($session, $learner);
        }

        return redirect()->away($url)->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
