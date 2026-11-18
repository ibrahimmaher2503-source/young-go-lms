<?php

declare(strict_types=1);

namespace App\Domain\Cohorts;

use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Public availability of courses that run in cohorts (T-P2-04): the catalog filter and the badges on the course cards.
 * One definition of "open", "seats left" and "starts soon" that reuses the cohort rules (enrolment window in Cairo time,
 * seated members against capacity). A course with no cohort at all (recorded, on demand) is open for enrolment.
 */
final class CohortAvailability
{
    public const string OPEN = 'open';

    public const string SEATS = 'seats';

    public const string SOON = 'soon';

    public const string SUBSCRIPTION = 'subscription';

    /** A cohort that starts within this many days counts as "starting soon". */
    public const int SOON_DAYS = 14;

    /** @return list<string> */
    public static function filters(): array
    {
        return [self::OPEN, self::SEATS, self::SOON, self::SUBSCRIPTION];
    }

    /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public static function constrain(Builder $query, string $availability): Builder
    {
        $today = now('Africa/Cairo')->toDateString();

        return match ($availability) {
            self::OPEN => $query->where('courses.access_mode', '!=', Course::ACCESS_SUBSCRIPTION)->where(static fn (Builder $open): Builder => $open
                ->whereDoesntHave('cohorts')
                ->orWhereHas('cohorts', static fn (Builder $cohorts): Builder => self::withRoom(self::enrolling($cohorts, $today)))),
            self::SEATS => $query->whereHas('cohorts', static fn (Builder $cohorts): Builder => self::withRoom(self::enrolling($cohorts, $today))->whereNotNull('capacity')),
            self::SOON => $query->whereHas('cohorts', static fn (Builder $cohorts): Builder => self::enrolling($cohorts, $today)
                ->whereDate('starts_on', '>=', $today)
                ->whereDate('starts_on', '<=', Carbon::parse($today)->addDays(self::SOON_DAYS)->toDateString())),
            self::SUBSCRIPTION => $query->whereIn('courses.access_mode', [Course::ACCESS_SUBSCRIPTION, Course::ACCESS_BOTH]),
            default => $query,
        };
    }

    /**
     * Badge data for a page of courses, one cohort query for all of them.
     *
     * @param  iterable<Course>  $courses
     * @return array<int, array{cohorts: bool, open: bool, seats_left: int|null, starts_on: Carbon|null}>
     */
    public static function forCourses(iterable $courses): array
    {
        $ids = [];
        foreach ($courses as $course) {
            $ids[] = $course->getKey();
        }
        if ($ids === []) {
            return [];
        }

        $cohorts = Cohort::query()->whereIn('course_id', $ids)
            ->withCount(['members as seated_count' => static fn ($members) => $members->where('status', CohortMember::SEATED)])
            ->get()->groupBy('course_id');
        $limit = now('Africa/Cairo')->startOfDay()->addDays(self::SOON_DAYS);
        $out = [];

        foreach ($ids as $id) {
            $all = $cohorts->get($id, collect());
            $open = $all->filter(static fn (Cohort $cohort): bool => $cohort->enrollmentIsOpen() && ($cohort->capacity === null || $cohort->capacity > (int) $cohort->getAttribute('seated_count')));
            $limited = $open->filter(static fn (Cohort $cohort): bool => $cohort->capacity !== null)
                ->map(static fn (Cohort $cohort): int => max(0, (int) $cohort->capacity - (int) $cohort->getAttribute('seated_count')));
            $soon = $open->map(static fn (Cohort $cohort): ?Carbon => $cohort->starts_on)->filter(static fn (?Carbon $date): bool => $date !== null
                && $date->greaterThanOrEqualTo(now('Africa/Cairo')->startOfDay()) && $date->lessThanOrEqualTo($limit))->sort()->first();

            $out[(int) $id] = [
                'cohorts' => $all->isNotEmpty(),
                'open' => $all->isEmpty() || $open->isNotEmpty(),
                'seats_left' => $limited->isEmpty() ? null : (int) $limited->min(),
                'starts_on' => $soon,
            ];
        }

        return $out;
    }

    /**
     * @param  Builder<Cohort>  $cohorts
     * @return Builder<Cohort>
     */
    private static function enrolling(Builder $cohorts, string $today): Builder
    {
        return $cohorts->where('status', Cohort::OPEN)
            ->where(static fn (Builder $from): Builder => $from->whereNull('enrollment_opens_on')->orWhereDate('enrollment_opens_on', '<=', $today))
            ->where(static fn (Builder $to): Builder => $to->whereNull('enrollment_closes_on')->orWhereDate('enrollment_closes_on', '>=', $today));
    }

    /**
     * @param  Builder<Cohort>  $cohorts
     * @return Builder<Cohort>
     */
    private static function withRoom(Builder $cohorts): Builder
    {
        return $cohorts->where(static fn (Builder $room): Builder => $room->whereNull('capacity')->orWhereRaw(
            'cohorts.capacity > (select count(*) from cohort_members where cohort_members.cohort_id = cohorts.id and cohort_members.status = ?)',
            [CohortMember::SEATED],
        ));
    }
}
