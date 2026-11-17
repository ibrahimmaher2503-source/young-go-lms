<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Shared\Search\HasSearchText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

final class BlogTag extends Model
{
    use HasSearchText;
    use HasTranslations;

    protected $table = 'tags';

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $fillable = ['name', 'slug_ar', 'slug_en'];

    /** @return BelongsToMany<BlogPost, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag', 'tag_id', 'blog_post_id');
    }

    protected function searchTextAttributes(): array
    {
        return ['name'];
    }
}
