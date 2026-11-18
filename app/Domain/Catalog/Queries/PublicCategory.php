<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Reads for the public category landing pages (T-P2-09). Only active categories are public; courses come from CatalogQuery (published only). */
final class PublicCategory
{
    /** Resolve an active category by the slug of either language, or the numeric id. */
    public function find(string $key): ?Category
    {
        return Category::query()
            ->where('status', 'active')
            ->where(static function (Builder $match) use ($key): void {
                $match->where('slug_ar', $key)->orWhere('slug_en', $key);
                if (ctype_digit($key)) {
                    $match->orWhere('id', (int) $key);
                }
            })
            ->first();
    }

    /**
     * Active sub-categories that have at least one published course.
     *
     * @return Collection<int, Category>
     */
    public function children(Category $category): Collection
    {
        $used = $this->usedIds(withParents: false);

        return Category::query()->where('parent_id', $category->getKey())->where('status', 'active')
            ->whereIn('id', $used)->orderBy('sort')->orderBy('id')->get();
    }

    /**
     * Active categories worth indexing: they (or a sub-category) hold at least one published course.
     *
     * @return Collection<int, Category>
     */
    public function listed(): Collection
    {
        return Category::query()->where('status', 'active')->whereIn('id', $this->usedIds(withParents: true))->orderBy('id')->get();
    }

    /**
     * @return list<int>
     */
    private function usedIds(bool $withParents = true): array
    {
        $ids = Course::query()->where('status', CourseStatus::Published)->whereNotNull('category_id')->distinct()->pluck('category_id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        if ($withParents && $ids !== []) {
            $parents = Category::query()->whereIn('id', $ids)->whereNotNull('parent_id')->pluck('parent_id')
                ->map(static fn (mixed $id): int => (int) $id)->all();
            $ids = array_values(array_unique([...$ids, ...$parents]));
        }

        return $ids;
    }

    /** URL key in the given locale: its slug, falling back to the other locale's slug and then the id. */
    public static function keyFor(Category $category, string $locale): string
    {
        $slugs = [(string) $category->getAttribute('slug_'.$locale), (string) $category->slug_ar, (string) $category->slug_en];

        return collect($slugs)->first(static fn (string $slug): bool => $slug !== '') ?? (string) $category->getKey();
    }
}
