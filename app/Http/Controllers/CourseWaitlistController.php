<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Cohorts\Actions\JoinWaitlist;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortAvailability;
use App\Domain\Identity\ActingLearner;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/** X2: the learner joins the waitlist of a full cohort from the course page. The seat rules live in JoinWaitlist; this only picks the cohort. */
final class CourseWaitlistController
{
    public function __invoke(int $course, ActingLearner $acting, JoinWaitlist $join): RedirectResponse
    {
        $found = Course::query()->findOrFail($course);
        $learner = $acting->current();
        abort_unless($learner instanceof User, 403);

        $state = CohortAvailability::stateFor($found, $learner);
        $cohort = $state['waitlist_cohort'] === null ? null : Cohort::query()->find($state['waitlist_cohort']);
        if (! $cohort instanceof Cohort) {
            throw ValidationException::withMessages(['cohort' => __('cohorts.not_full')]);
        }

        $join($cohort, $learner);

        return redirect()->to(route('catalog.show', ['course' => PublicCourseDetail::key($found)]))->with('waitlist_notice', __('cohorts.waitlist_joined'));
    }
}
