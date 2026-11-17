<?php

declare(strict_types=1);

namespace App\Domain\Content\Queries;

use App\Domain\Shared\Search\ArabicNormalizer;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/** Everything the public blog shows. Only live posts (not draft, publish time reached) are ever returned. */
final class PublicBlog
{
    public const int PER_PAGE = 9;

    /**
     * @param  array{q?: string, category?: string, tag?: string}  $filters  category and tag are slugs of either language
     * @return LengthAwarePaginator<int, BlogPost>
     */
    public function posts(array $filters = []): LengthAwarePaginator
    {
        $query = BlogPost::query()->live()->with(['category', 'author', 'tags']);

        if (filled($filters['category'] ?? null)) {
            $category = $this->categoryBySlug($filters['category']);
            $query->where('blog_posts.blog_category_id', $category?->getKey() ?? 0);
        }
        if (filled($filters['tag'] ?? null)) {
            $tag = BlogTag::query()->where(static fn (Builder $match) => $match->where('slug_ar', $filters['tag'])->orWhere('slug_en', $filters['tag']))->first();
            $query->whereHas('tags', static fn (Builder $tags) => $tags->whereKey($tag?->getKey() ?? 0));
        }
        if (filled($filters['q'] ?? null)) {
            $query->whereLike('blog_posts.search_text', '%'.ArabicNormalizer::normalize($filters['q']).'%');
        }

        return $query->orderByDesc('blog_posts.published_at')->orderByDesc('blog_posts.id')->paginate(self::PER_PAGE);
    }

    /** Resolve a live post by the slug of either language, or its id. */
    public function find(string $key): ?BlogPost
    {
        return BlogPost::query()->live()
            ->where(static function (Builder $match) use ($key): void {
                $match->where('blog_posts.slug_ar', $key)->orWhere('blog_posts.slug_en', $key);
                if (ctype_digit($key)) {
                    $match->orWhere('blog_posts.id', (int) $key);
                }
            })
            ->with(['category', 'author', 'tags'])
            ->first();
    }

    /**
     * Live posts in the same category or sharing a tag, newest first.
     *
     * @return Collection<int, BlogPost>
     */
    public function related(BlogPost $post, int $limit = 3): Collection
    {
        $tagIds = $post->tags->modelKeys();

        return BlogPost::query()->live()->whereKeyNot($post->getKey())
            ->where(static function (Builder $match) use ($post, $tagIds): void {
                $match->whereIn('blog_posts.id', []);
                if ($post->blog_category_id !== null) {
                    $match->orWhere('blog_posts.blog_category_id', $post->blog_category_id);
                }
                if ($tagIds !== []) {
                    $match->orWhereHas('tags', static fn (Builder $tags) => $tags->whereIn('tags.id', $tagIds));
                }
            })
            ->with(['category', 'author', 'tags'])
            ->orderByDesc('blog_posts.published_at')->orderByDesc('blog_posts.id')
            ->limit($limit)->get();
    }

    /**
     * Active categories that have at least one live post.
     *
     * @return Collection<int, BlogCategory>
     */
    public function categories(): Collection
    {
        return BlogCategory::query()->where('status', BlogCategory::ACTIVE)
            ->whereHas('posts', static fn (Builder $posts) => $posts->live())
            ->orderBy('sort')->orderBy('id')->get();
    }

    /**
     * Tags used by at least one live post.
     *
     * @return Collection<int, BlogTag>
     */
    public function tags(): Collection
    {
        return BlogTag::query()->whereHas('posts', static fn (Builder $posts) => $posts->live())->orderBy('id')->get();
    }

    /** URL key of a post, category or tag in a language: its slug there, else the other language's slug, else the id. */
    public static function keyFor(BlogPost|BlogCategory|BlogTag $model, string $locale): string
    {
        $slugs = [(string) $model->getAttribute('slug_'.$locale), (string) $model->getAttribute('slug_ar'), (string) $model->getAttribute('slug_en')];

        return collect($slugs)->first(static fn (string $slug): bool => $slug !== '') ?? (string) $model->getKey();
    }

    private function categoryBySlug(string $slug): ?BlogCategory
    {
        return BlogCategory::query()->where('status', BlogCategory::ACTIVE)
            ->where(static fn (Builder $match) => $match->where('slug_ar', $slug)->orWhere('slug_en', $slug))->first();
    }
}
