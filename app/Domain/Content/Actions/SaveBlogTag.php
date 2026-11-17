<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Creates or updates a blog tag (input validated by the Filament form) and audits it. */
final class SaveBlogTag
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, ?BlogTag $tag, array $data): BlogTag
    {
        Gate::forUser($actor)->authorize('content.blog.manage');

        $tag ??= new BlogTag;
        $before = $tag->exists ? $tag->only(['slug_ar', 'slug_en']) : null;
        $tag->fill($data)->save();

        $this->audit->record('blog.tag_saved', $tag, (int) $actor->getKey(), $before, $tag->only(['slug_ar', 'slug_en']));

        return $tag;
    }
}
