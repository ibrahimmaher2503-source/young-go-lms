<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Content\BlogPostStatus;
use App\Domain\Shared\Search\HasSearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property BlogPostStatus $status
 * @property Carbon|null $published_at
 */
final class BlogPost extends Model
{
    use HasSearchText;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title', 'excerpt', 'body', 'featured_image_alt', 'seo_title', 'seo_description'];

    protected $fillable = [
        'blog_category_id', 'author_id', 'slug_ar', 'slug_en', 'title', 'excerpt', 'body', 'featured_image', 'featured_image_alt',
        'status', 'published_at', 'seo_title', 'seo_description',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => BlogPostStatus::class, 'published_at' => 'datetime'];
    }

    /** @return BelongsTo<BlogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsToMany<BlogTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'blog_post_id', 'tag_id');
    }

    /**
     * The one definition of "public": not a draft and its publish time has come. A scheduled post therefore appears by itself.
     *
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('blog_posts.status', [BlogPostStatus::Scheduled->value, BlogPostStatus::Published->value])
            ->whereNotNull('blog_posts.published_at')
            ->where('blog_posts.published_at', '<=', now());
    }

    public function isLive(): bool
    {
        return $this->status !== BlogPostStatus::Draft && $this->published_at !== null && $this->published_at->lte(now());
    }

    protected function searchTextAttributes(): array
    {
        return ['title', 'excerpt', 'body'];
    }
}
