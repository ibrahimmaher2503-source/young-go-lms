<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Creates or updates a blog category (input validated by the Filament form) and audits it. */
final class SaveBlogCategory
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, ?BlogCategory $category, array $data): BlogCategory
    {
        Gate::forUser($actor)->authorize('content.blog.manage');

        $category ??= new BlogCategory;
        $before = $category->exists ? $category->only(['slug_ar', 'slug_en', 'sort', 'status']) : null;
        $category->fill($data)->save();

        $this->audit->record('blog.category_saved', $category, (int) $actor->getKey(), $before, $category->only(['slug_ar', 'slug_en', 'sort', 'status']));

        return $category;
    }
}
