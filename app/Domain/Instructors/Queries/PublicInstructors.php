<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Queries;

use App\Models\InstructorProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The one definition of a public instructor (T-P2-09): the profile is approved, the account is active and still holds an instructor
 * role. The profile page, the sitemap, the course-page link and the structured data all read through here, so they never disagree.
 */
final class PublicInstructors
{
    public const array ROLES = ['instructor_academy', 'instructor_independent'];

    /** @return Builder<InstructorProfile> */
    public function query(): Builder
    {
        $guard = (string) config('permissions.guard', 'web');

        return InstructorProfile::query()
            ->where('instructor_profiles.status', InstructorProfile::APPROVED)
            ->whereHas('user', static fn (Builder $user): Builder => $user->where('users.status', 'active')
                ->whereHas('roles', static fn (Builder $roles): Builder => $roles->where('guard_name', $guard)->whereIn('name', self::ROLES)));
    }

    /** Resolve by the slug of either language, or the numeric id. */
    public function find(string $key): ?InstructorProfile
    {
        return $this->query()
            ->where(static function (Builder $match) use ($key): void {
                $match->where('instructor_profiles.slug_ar', $key)->orWhere('instructor_profiles.slug_en', $key);
                if (ctype_digit($key)) {
                    $match->orWhere('instructor_profiles.id', (int) $key);
                }
            })
            ->with(['user', 'disciplines' => static fn ($categories) => $categories->where('categories.status', 'active')])
            ->first();
    }

    /**
     * Public profiles of the given accounts, keyed by user id (used to link instructor names on the course page).
     *
     * @param  iterable<int, int|string>  $userIds
     * @return Collection<int, InstructorProfile>
     */
    public function forUsers(iterable $userIds): Collection
    {
        $ids = collect($userIds)->map(static fn (int|string $id): int => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return new Collection;
        }

        return $this->query()->whereIn('instructor_profiles.user_id', $ids)->with('user')->get()->keyBy('user_id');
    }

    /** URL key in the given locale: its slug, falling back to the other locale's slug and then the id. */
    public static function keyFor(InstructorProfile $profile, string $locale): string
    {
        $slugs = [(string) $profile->getAttribute('slug_'.$locale), (string) $profile->slug_ar, (string) $profile->slug_en];

        return collect($slugs)->first(static fn (string $slug): bool => $slug !== '') ?? (string) $profile->getKey();
    }
}
