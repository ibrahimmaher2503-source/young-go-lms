<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\CourseSeo;
use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Cohorts\CohortAvailability;
use App\Domain\Commerce\Queries\CheckoutLearners;
use App\Domain\Commerce\Queries\CheckoutOffers;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Seo\PublicUrl;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/** Public course page (YG-WEB-004). A course that is not published answers 404, exactly like one that does not exist. */
final class PublicCourseController
{
    public function __invoke(string $course, PublicCourseDetail $details, ActingLearner $acting, EntitlementService $access, PublicInstructors $instructors, CheckoutOffers $offers, CheckoutLearners $learners): View
    {
        $found = $details->find($course);
        abort_if($found === null, 404);

        $curriculum = $details->curriculum($found);
        $learner = $acting->current();
        $key = PublicCourseDetail::key($found);

        $profiles = $instructors->forUsers($found->instructors->modelKeys());
        $market = Market::Egypt;
        $freeEnrollable = $found->access_mode === Course::ACCESS_FREE;
        $authUser = Auth::user();
        $isParentContext = $acting->isParentContext();

        $enrollLearners = collect();
        $enrollOwned = [];
        if ($authUser instanceof User && $freeEnrollable && ! $isParentContext && $authUser->hasRole('parent')) {
            $enrollLearners = $learners($authUser);
            foreach ($enrollLearners as $option) {
                $enrollOwned[$option->getKey()] = $access->currentFor($option, $found) !== null;
            }
        }

        return view('catalog.show', [
            'course' => $found,
            'profiles' => $profiles,
            'curriculum' => $curriculum,
            'seo' => CourseSeo::for($found, PublicUrl::course($found, app()->getLocale()), $curriculum['lessons'], $profiles),
            'key' => $key,
            'entitled' => $learner instanceof User && $access->currentFor($learner, $found) !== null,
            'signedIn' => $learner instanceof User,
            'seats' => CohortAvailability::stateFor($found, $learner instanceof User ? $learner : null),
            'market' => $market,
            'price' => $offers->coursePrice($found, $market),
            'coursePlans' => $offers->plansForCourse($found, $market),
            'courseBundles' => $offers->bundlesForCourse($found, $market),
            'freeEnrollable' => $freeEnrollable,
            'enrollLearners' => $enrollLearners,
            'enrollOwned' => $enrollOwned,
            'isParentContext' => $isParentContext,
        ]);
    }
}
