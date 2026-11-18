<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Engagement\Actions\SubmitCourseReview;
use App\Domain\Identity\ActingLearner;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** T-P9-02: an entitled learner sends or edits their review. It stays private until moderation publishes it. */
final class CourseReviewController
{
    public function store(Request $request, int $course, ActingLearner $acting, SubmitCourseReview $submit): RedirectResponse
    {
        $found = Course::query()->findOrFail($course);
        $learner = $request->user();
        abort_unless($learner instanceof User && ! $acting->isParentContext(), 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:'.config('engagement.reviews.body_min'), 'max:'.config('engagement.reviews.body_max')],
        ]);

        $submit($learner, $found, (int) $data['rating'], (string) $data['body']);

        return redirect()->to(route('catalog.show', ['course' => PublicCourseDetail::key($found)]).'#reviews')->with('review_notice', __('engagement.submitted'));
    }
}
