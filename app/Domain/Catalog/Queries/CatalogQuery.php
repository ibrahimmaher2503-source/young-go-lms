<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Models\AgeBand;
use App\Domain\Cohorts\CohortAvailability;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Entitlements\EnrollmentReport;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Search\ArabicNormalizer;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;

final class CatalogQuery
{
    public const string SORT_NEWEST = 'newest';

    public const string SORT_PRICE_ASC = 'price_asc';

    public const string SORT_PRICE_DESC = 'price_desc';

    public const string SORT_POPULAR = 'popular';

    /** @return list<string> */
    public static function sorts(): array
    {
        return [self::SORT_NEWEST, self::SORT_PRICE_ASC, self::SORT_PRICE_DESC, self::SORT_POPULAR];
    }

    /** @return Builder<Course> */
    public function published(): Builder
    {
        return Course::query()->where('courses.status', CourseStatus::Published);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Course>
     */
    public function matching(array $filters, ?AgeBand $band): Builder
    {
        $query = $this->published()->with(['category', 'instructors']);
        $search = ArabicNormalizer::normalize((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->whereLike('search_text', '%'.$search.'%');
        }

        if (isset($filters['category'])) {
            $id = (int) $filters['category'];
            $query->whereHas('category', static fn (Builder $categories): Builder => $categories
                ->where('status', 'active')
                ->where(static fn (Builder $match): Builder => $match->whereKey($id)->orWhere('parent_id', $id)));
        }

        if (isset($filters['age'])) {
            $age = (int) $filters['age'];
            $this->ageOverlap($query, $age, $age);
        } elseif ($band !== null) {
            $this->ageOverlap($query, $band->min, $band->max);
        }

        foreach (['level' => 'level', 'format' => 'format', 'language' => 'content_language'] as $field => $column) {
            if (isset($filters[$field])) {
                $query->where($column, $filters[$field]);
            }
        }

        if (isset($filters['instructor'])) {
            $query->whereHas('instructors', static fn (Builder $assigned): Builder => $assigned
                ->whereKey((int) $filters['instructor'])->where('users.status', 'active'));
        }

        if (isset($filters['access'])) {
            $query->whereIn('access_mode', match ($filters['access']) {
                'free' => [Course::ACCESS_FREE],
                'paid' => Course::INDIVIDUAL_ACCESS_MODES,
                'subscription' => [Course::ACCESS_SUBSCRIPTION, Course::ACCESS_BOTH],
                default => [],
            });
        }

        if (isset($filters['availability'])) {
            CohortAvailability::constrain($query, (string) $filters['availability']);
        }

        $this->priceRange($query, $filters, Market::Egypt);

        return $this->sorted($query, (string) ($filters['sort'] ?? self::SORT_NEWEST), Market::Egypt);
    }

    /**
     * Price range in major units of the market currency. A free course counts as price 0; a course with no price in the market
     * (subscription only) never matches a range.
     *
     * @param  Builder<Course>  $query
     * @param  array<string, mixed>  $filters
     */
    private function priceRange(Builder $query, array $filters, Market $market): void
    {
        $min = isset($filters['price_min']) ? (int) round((float) $filters['price_min'] * 100) : null;
        $max = isset($filters['price_max']) ? (int) round((float) $filters['price_max'] * 100) : null;

        if ($min === null && $max === null) {
            return;
        }

        $query->where(static function (Builder $range) use ($min, $max, $market): void {
            if (($min ?? 0) <= 0) {
                $range->where('courses.access_mode', Course::ACCESS_FREE);
            }

            $range->orWhere(static fn (Builder $paid): Builder => $paid->whereIn('courses.access_mode', Course::INDIVIDUAL_ACCESS_MODES)
                ->whereExists(CoursePrice::query()->selectRaw('1')
                    ->whereColumn('course_prices.course_id', 'courses.id')
                    ->where('course_prices.market', $market->value)
                    ->when($min !== null, static fn ($prices) => $prices->where('course_prices.amount_minor', '>=', $min))
                    ->when($max !== null, static fn ($prices) => $prices->where('course_prices.amount_minor', '<=', $max))));
        });
    }

    /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    private function sorted(Builder $query, string $sort, Market $market): Builder
    {
        if ($sort === self::SORT_PRICE_ASC || $sort === self::SORT_PRICE_DESC) {
            $asc = $sort === self::SORT_PRICE_ASC;
            // Free = 0; no price in this market (subscription only) always sorts last.
            $query->orderByRaw(
                'coalesce(case when courses.access_mode = ? then 0 end, (select course_prices.amount_minor from course_prices where course_prices.course_id = courses.id and course_prices.market = ?), '.($asc ? '999999999999999' : '-1').') '.($asc ? 'asc' : 'desc'),
                [Course::ACCESS_FREE, $market->value],
            );
        }

        if ($sort === self::SORT_POPULAR && ($counts = EnrollmentReport::activeCountsByCourse()) !== []) {
            $cases = implode(' ', array_fill(0, count($counts), 'when ? then ?'));
            $query->orderByRaw('case courses.id '.$cases.' else 0 end desc', array_merge(...array_map(null, array_keys($counts), array_values($counts))));
        }

        return $query->orderByDesc('courses.created_at')->orderByDesc('courses.id');
    }

    /** @param Builder<Course> $query */
    private function ageOverlap(Builder $query, int $min, ?int $max): void
    {
        $query->where(static fn (Builder $age): Builder => $age->whereNull('max_age')->orWhere('max_age', '>=', $min));

        if ($max !== null) {
            $query->where(static fn (Builder $age): Builder => $age->whereNull('min_age')->orWhere('min_age', '<=', $max));
        }
    }
}
