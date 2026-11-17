<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\CourseSeo;
use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Models\User;
use Illuminate\Contracts\View\View;

/** Public course page (YG-WEB-004). A course that is not published answers 404, exactly like one that does not exist. */
final class PublicCourseController
{
    public function __invoke(string $course, PublicCourseDetail $details, ActingLearner $acting, EntitlementService $access): View
    {
        $found = $details->find($course);
        abort_if($found === null, 404);

        $curriculum = $details->curriculum($found);
        $learner = $acting->current();
        $key = PublicCourseDetail::key($found);

        return view('catalog.show', [
            'course' => $found,
            'curriculum' => $curriculum,
            'seo' => CourseSeo::for($found, route('catalog.show', ['course' => $key]), $curriculum['lessons']),
            'key' => $key,
            'entitled' => $learner instanceof User && $access->currentFor($learner, $found) !== null,
            'signedIn' => $learner instanceof User,
        ]);
    }
}
