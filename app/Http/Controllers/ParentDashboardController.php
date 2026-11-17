<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\GuardianAccess;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Progress\Queries\LearnerCourseRows;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * A parent's read-only overview of one linked child at a time. Only verified, active links are listed and an unlinked
 * child id is a 404. No AI conversation content exists here or anywhere a parent can reach (O-016).
 */
final class ParentDashboardController
{
    public function __construct(private readonly LearnerCourseRows $rows) {}

    public function __invoke(Request $request): View
    {
        $parent = $request->user();
        abort_unless($parent instanceof User && $parent->hasRole('parent') && $parent->can('guardian.child.manage'), 403);

        $children = GuardianChildLink::query()->with('child')->where('guardian_id', $parent->getKey())
            ->where('status', GuardianChildLink::STATUS_VERIFIED)->orderBy('id')->get()
            ->map(fn (GuardianChildLink $link): User => $link->child)
            ->filter(fn (User $child): bool => GuardianAccess::parentCanAccessChild($parent, $child)->allowed() && $child->getAttribute('status') === 'active')
            ->values();

        $selected = null;
        if ($request->filled('child')) {
            $selected = $children->firstWhere('id', (int) $request->query('child'));
            abort_if($selected === null, 404); // a child who is not linked and verified to this parent does not exist here
        }
        $selected ??= $children->first();

        $courses = $selected === null ? collect() : $this->rows->for($selected);
        $active = $courses->where('access', 'active');

        return view('parent.dashboard', [
            'parent' => $parent,
            'children' => $children,
            'selected' => $selected,
            'courses' => $courses->values(),
            'summary' => [
                'courses' => $courses->count(),
                'completed' => $courses->where('status', 'completed')->count(),
                'lessons_done' => (int) $active->sum('done'),
                'lessons_total' => (int) $active->sum('total'),
                'average' => $active->isEmpty() ? 0 : (int) round($active->avg('percent')),
            ],
        ]);
    }
}
