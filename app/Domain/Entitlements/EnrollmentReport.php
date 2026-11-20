<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use App\Domain\Shared\Markets\Market;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment counts per course for the reports module. It lives in the Entitlements module because only that module reads the
 * entitlements table (I01). Counts are distinct learners, so a renewal, a second source or a retry never inflates them.
 */
final class EnrollmentReport
{
    /** One row per learner and course, including access that has since ended. */
    public static function firstEnrollments(): Builder
    {
        return Entitlement::query()->groupBy('course_id', 'learner_id')
            ->selectRaw('course_id, learner_id, min(starts_at) as enrolled_at')->toBase();
    }

    /**
     * Distinct learners with access right now (T-P9-07 dashboards). A learner in two courses counts once.
     *
     * @param  list<int>|null  $courseIds  null = every course, an empty list = none
     */
    public static function activeLearners(?array $courseIds): int
    {
        if ($courseIds === []) {
            return 0;
        }

        $now = now()->utc()->format('Y-m-d H:i:s');

        return (int) Entitlement::query()->where('status', Entitlement::ACTIVE)->where('starts_at', '<=', $now)
            ->where(static fn ($open) => $open->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->when($courseIds !== null, static fn ($query) => $query->whereIn('course_id', $courseIds ?? []))
            ->distinct()->count('learner_id');
    }

    /** Group current access by course for the catalog's popularity sort (T-P2-04). */
    public static function activeCountsByCourse(): Builder
    {
        $now = now()->utc()->format('Y-m-d H:i:s');

        return Entitlement::query()->where('status', Entitlement::ACTIVE)->where('starts_at', '<=', $now)
            ->where(static fn ($open) => $open->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->groupBy('course_id')->selectRaw('course_id, count(distinct learner_id) as learners')->toBase();
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<int, array{new: int, active: int, total: int}> total = learners who ever had access, new = learners whose first access started in the range, active = learners with access now
     */
    public static function forCourses(array $courseIds, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($courseIds === []) {
            return [];
        }

        $now = now()->utc()->format('Y-m-d H:i:s');
        $perLearner = Entitlement::query()->whereIn('course_id', $courseIds)->groupBy('course_id', 'learner_id')
            ->selectRaw('course_id, learner_id, min(starts_at) as first_at, max(case when status = ? and starts_at <= ? and (ends_at is null or ends_at > ?) then 1 else 0 end) as is_active', [Entitlement::ACTIVE, $now, $now]);

        $rows = DB::query()->fromSub($perLearner->toBase(), 'per_learner')->groupBy('course_id')
            ->selectRaw('course_id, count(*) as total, sum(case when first_at >= ? and first_at <= ? then 1 else 0 end) as new_count, sum(is_active) as active_count', [$from->clone()->utc()->format('Y-m-d H:i:s'), $to->clone()->utc()->format('Y-m-d H:i:s')])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->course_id] = ['new' => (int) $row->new_count, 'active' => (int) $row->active_count, 'total' => (int) $row->total];
        }

        return $out;
    }

    /**
     * First-time enrollments grouped across fixed date buckets for dashboard trend charts (I01).
     *
     * @param  list<int>|null  $courseIds  null = every course, an empty list = none
     * @return array{days: int, total: int, max: int, points: list<array{label: string, range: string, count: int}>}
     */
    public static function trend(int $days = 30, ?array $courseIds = null): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $step = match ($days) {
            7 => 1,
            90 => 9,
            default => 3,
        };
        $bucketCount = intdiv($days, $step);
        $tz = Market::Egypt->timezone();
        $locale = app()->getLocale();
        $today = CarbonImmutable::now($tz)->startOfDay();
        $start = $today->subDays($days - 1);
        $end = $today->endOfDay();

        $counts = array_fill(0, $bucketCount, 0);
        if ($courseIds !== []) {
            $firstQuery = self::firstEnrollments();
            $timestamps = DB::query()->fromSub($firstQuery, 'first_enrollments')
                ->when($courseIds !== null, static fn (Builder $query): Builder => $query->whereIn('course_id', $courseIds ?? []))
                ->whereBetween('enrolled_at', [$start->clone()->utc()->format('Y-m-d H:i:s'), $end->clone()->utc()->format('Y-m-d H:i:s')])
                ->pluck('enrolled_at');

            foreach ($timestamps as $raw) {
                $local = CarbonImmutable::parse((string) $raw, 'UTC')->setTimezone($tz)->startOfDay();
                $dayIndex = (int) $start->diffInDays($local, false);
                if ($dayIndex >= 0 && $dayIndex < $days) {
                    $bucket = min($bucketCount - 1, intdiv($dayIndex, $step));
                    $counts[$bucket]++;
                }
            }
        }

        $points = [];
        for ($i = 0; $i < $bucketCount; $i++) {
            $bucketStart = $start->addDays($i * $step);
            $bucketEnd = $bucketStart->addDays($step - 1);
            $label = $bucketStart->locale($locale)->translatedFormat('j M');
            $range = $step === 1
                ? $label
                : $bucketStart->locale($locale)->translatedFormat('j M').' – '.$bucketEnd->locale($locale)->translatedFormat('j M');
            $points[] = ['label' => $label, 'range' => $range, 'count' => $counts[$i]];
        }

        return [
            'days' => $days,
            'total' => array_sum($counts),
            'max' => max($counts) > 0 ? max($counts) : 0,
            'points' => $points,
        ];
    }
}
