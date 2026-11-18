<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\Models\ViewAsSession;
use App\Domain\Progress\Queries\LearnerCourseDetail;
use App\Domain\Progress\Queries\LearnerCourseRows;
use App\Domain\Support\Actions\RecordViewAsPageView;
use App\Domain\Support\Actions\StopViewAs;
use App\Domain\Support\ViewAsState;
use App\Filament\Pages\LearnerThreeSixtyPage;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Support "view as learner" (ED-027): the learner's own course list and course outline as they would see them, read-only.
 * The staff member is never signed in as the learner. These pages read through the same access rules a learner meets
 * (LearnerCourseRows, EntitledCurriculumQuery), write no progress and offer no assistant. Every page view is audited.
 */
final class SupportViewAsController
{
    public function __construct(private readonly ViewAsState $state, private readonly RecordViewAsPageView $record) {}

    public function home(LearnerCourseRows $rows): View
    {
        $session = $this->session();
        $learner = $session->learner;
        abort_if($learner === null, 404);
        ($this->record)($session, 'courses');

        return view('support.view-as.home', ['session' => $session, 'learner' => $learner, 'courses' => $rows->for($learner, false)]);
    }

    public function course(int $course, EntitledCurriculumQuery $curriculum, EntitlementService $access): View
    {
        $session = $this->session();
        $learner = $session->learner;
        abort_if($learner === null, 404);

        $model = Course::query()->findOrFail($course);
        // Only courses the learner has (or had) access to are shown: staff cannot browse arbitrary courses through this view.
        abort_unless(collect($this->coursesOf($learner))->contains($model->getKey()), 404);
        ($this->record)($session, 'course', ['course_id' => $model->getKey()]);

        return view('support.view-as.course', [
            'session' => $session,
            'learner' => $learner,
            'course' => $model,
            'decision' => $access->canAccessCourse($learner, $model),
            'sections' => $curriculum->for($learner, $model),
            'lessons' => LearnerCourseDetail::for($learner, $model),
        ]);
    }

    public function stop(StopViewAs $stop): RedirectResponse
    {
        // An expired session was already ended by ViewAsState; Stop then simply returns to the panel.
        $session = $this->state->current();
        if ($session === null) {
            return redirect(LearnerThreeSixtyPage::getUrl(panel: 'admin'));
        }

        $learnerId = $session->learner_id;
        $stop($session);

        return redirect(LearnerThreeSixtyPage::getUrl(['learner' => $learnerId], panel: 'admin'));
    }

    private function session(): ViewAsSession
    {
        $session = $this->state->current();
        abort_if($session === null, 403, __('support.no_session'));

        return $session;
    }

    /** @return list<int> */
    private function coursesOf(User $learner): array
    {
        return app(LearnerCourseRows::class)->for($learner, false)->map(static fn (array $row): int => (int) $row['course']->getKey())->all();
    }
}
