<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Shared\Search\HasSearchText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

final class BlogCategory extends Model
{
    use HasSearchText;
    use HasTranslations;

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $fillable = ['name', 'description', 'slug_ar', 'slug_en', 'sort', 'status'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /** @return HasMany<BlogPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    protected function searchTextAttributes(): array
    {
        return ['name', 'description'];
    }
}
