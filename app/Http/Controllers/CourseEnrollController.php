<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Entitlements\Actions\EnrollInFreeCourse;
use App\Domain\Identity\ActingLearner;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Self-enrollment (or parent enrollment for a verified child) in a free published course (T-P4-02). */
final class CourseEnrollController
{
    public function __invoke(Request $request, int $course, ActingLearner $acting, EnrollInFreeCourse $enroll, EntitledCurriculumQuery $curriculum): RedirectResponse
    {
        $found = Course::query()->find($course);
        abort_if($found === null || $found->status !== CourseStatus::Published, 404);

        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $data = $request->validate(['child_id' => ['nullable', 'integer', 'min:1']]);

        if ($request->filled('child_id')) {
            $targetId = (int) $data['child_id'];
            if ($targetId === (int) $actor->getKey()) {
                $learner = $actor;
            } else {
                $learner = User::query()->find($targetId);
                abort_if($learner === null, 404);
            }
        } else {
            $learner = $acting->current();
            abort_unless($learner instanceof User, 401);
        }

        ($enroll)($actor, $learner, $found);

        if ($acting->current()?->is($learner) === true) {
            $sections = $curriculum->for($learner, $found);
            $hasOpen = collect($sections)
                ->flatMap(static fn (array $section): array => $section['items'])
                ->contains(static fn (array $item): bool => $item['state'] !== 'locked');

            if ($hasOpen) {
                return redirect()->route('learn.course', ['course' => $found->getKey()]);
            }

            return redirect()
                ->route('catalog.show', ['course' => PublicCourseDetail::key($found)])
                ->with('enrollment_notice', __('course_page.enrolled'));
        }

        $name = (string) ($learner->display_name ?: $learner->name);

        return redirect()
            ->route('catalog.show', ['course' => PublicCourseDetail::key($found)])
            ->with('enrollment_notice', __('course_page.enrolled_child', ['name' => $name]));
    }
}
