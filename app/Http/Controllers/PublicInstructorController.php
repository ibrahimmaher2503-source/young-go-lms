<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Queries\CatalogQuery;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Seo\InstructorSeo;
use Illuminate\Contracts\View\View;

/** Public instructor profile (YG-GAP-CMS-001). Only approved profiles of active instructors; anything else answers 404. */
final class PublicInstructorController
{
    public function __invoke(string $instructor, PublicInstructors $instructors, CatalogQuery $catalog): View
    {
        $profile = $instructors->find($instructor);
        abort_if($profile === null, 404);

        $courses = $catalog->matching(['instructor' => $profile->user_id], null)->paginate(12);

        return view('instructors.show', [
            'profile' => $profile,
            'courses' => $courses,
            'seo' => InstructorSeo::for($profile, $courses->currentPage()),
        ]);
    }
}
