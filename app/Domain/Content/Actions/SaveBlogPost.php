<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Content\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a blog post (input is validated by the Filament form). Owns the publishing rules:
 * a scheduled post needs a future time; a published post gets its time (now when empty) and is stored as scheduled
 * when that time is still ahead. Audits created, updated, published, scheduled and unpublished.
 */
final class SaveBlogPost
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, ?BlogPost $post, array $data): BlogPost
    {
        Gate::forUser($actor)->authorize('content.blog.manage');

        $tags = array_values(array_unique(array_map(intval(...), (array) ($data['tags'] ?? []))));
        unset($data['tags']);

        $status = BlogPostStatus::from((string) ($data['status'] ?? BlogPostStatus::Draft->value));
        $at = filled($data['published_at'] ?? null) ? Carbon::parse((string) $data['published_at']) : null;
        $unchangedSchedule = $post !== null && $post->status === BlogPostStatus::Scheduled && $at !== null && $post->published_at?->equalTo($at) === true;

        if ($status === BlogPostStatus::Scheduled && ($at === null || ($at->lte(now()) && ! $unchangedSchedule))) {
            throw ValidationException::withMessages(['published_at' => __('blog.schedule_future')]);
        }
        if ($status === BlogPostStatus::Published) {
            $at ??= $post->published_at ?? now();
            if ($at->isFuture()) {
                $status = BlogPostStatus::Scheduled;
            }
        }
        $data['status'] = $status->value;
        $data['published_at'] = $at;

        $before = $post?->only(['status', 'published_at', 'slug_ar', 'slug_en']);
        $wasStatus = $post?->status;

        $post = DB::transaction(function () use ($post, $data, $tags): BlogPost {
            $post ??= new BlogPost;
            $post->fill($data)->save();
            $post->tags()->sync($tags);

            return $post;
        });

        $event = match (true) {
            $wasStatus === null => 'blog.post_created',
            $wasStatus !== $status && $status === BlogPostStatus::Published => 'blog.post_published',
            $wasStatus !== $status && $status === BlogPostStatus::Scheduled => 'blog.post_scheduled',
            $wasStatus !== $status && $status === BlogPostStatus::Draft => 'blog.post_unpublished',
            default => 'blog.post_updated',
        };
        $this->audit->record($event, $post, (int) $actor->getKey(), $before, $post->only(['status', 'published_at', 'slug_ar', 'slug_en']));

        return $post;
    }
}
