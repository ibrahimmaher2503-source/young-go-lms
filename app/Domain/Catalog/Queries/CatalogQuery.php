<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Models\AgeBand;
use App\Domain\Shared\Search\ArabicNormalizer;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;

final class CatalogQuery
{
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

        return $query->latest()->orderByDesc('id');
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
