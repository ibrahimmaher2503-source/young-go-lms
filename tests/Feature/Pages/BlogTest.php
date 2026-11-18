<?php

declare(strict_types=1);

use App\Domain\Content\Actions\SaveBlogCategory;
use App\Domain\Content\Actions\SaveBlogPost;
use App\Domain\Content\Actions\SaveBlogTag;
use App\Domain\Content\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('enforces blog publication, future scheduling visibility, search/filter queries, and authorization', function (): void {
    $editor = User::factory()->create(['status' => 'active'])->assignRole('content_manager');
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');

    expect(fn () => app(SaveBlogCategory::class)($student, null, ['name' => ['en' => 'Nope'], 'slug_ar' => 'x', 'slug_en' => 'x', 'status' => 'active', 'sort' => 1]))
        ->toThrow(AuthorizationException::class);

    $cat = app(SaveBlogCategory::class)($editor, null, [
        'name' => ['ar' => 'ألوان', 'en' => 'Colors'],
        'slug_ar' => 'colors-ar',
        'slug_en' => 'colors-en',
        'status' => BlogCategory::ACTIVE,
        'sort' => 1,
    ]);
    $tag = app(SaveBlogTag::class)($editor, null, [
        'name' => ['ar' => 'أطفال', 'en' => 'Kids'],
        'slug_ar' => 'kids-ar',
        'slug_en' => 'kids-en',
    ]);

    $savePost = app(SaveBlogPost::class);

    // Scheduled without future timestamp is rejected
    expect(fn () => $savePost($editor, null, [
        'blog_category_id' => $cat->id,
        'author_id' => $editor->id,
        'title' => ['en' => 'Invalid Schedule'],
        'excerpt' => ['en' => 'Ex'],
        'body' => ['en' => 'Body'],
        'slug_en' => 'invalid-sched',
        'status' => BlogPostStatus::Scheduled->value,
        'published_at' => now()->subMinute()->toDateTimeString(),
    ]))->toThrow(ValidationException::class);

    // Draft post stays hidden (404)
    $draft = $savePost($editor, null, [
        'blog_category_id' => $cat->id,
        'author_id' => $editor->id,
        'title' => ['ar' => 'مسودة', 'en' => 'Draft Guide'],
        'excerpt' => ['ar' => 'مختصر', 'en' => 'Excerpt'],
        'body' => ['ar' => 'محتوى', 'en' => 'Secret draft body'],
        'slug_ar' => 'draft-guide-ar',
        'slug_en' => 'draft-guide-en',
        'status' => BlogPostStatus::Draft->value,
    ]);
    $this->get('/en/blog/draft-guide-en')->assertNotFound();

    // Published live post appears on index, category/tag filters, search, and detail page
    $live = $savePost($editor, null, [
        'blog_category_id' => $cat->id,
        'author_id' => $editor->id,
        'title' => ['ar' => 'أداء الرسم المائي', 'en' => 'Watercolor Performance'],
        'excerpt' => ['ar' => 'نص تعليمي', 'en' => 'Guide intro'],
        'body' => ['ar' => 'تلوين للمبتدئين', 'en' => 'Full brush guide'],
        'slug_ar' => 'water-perf-ar',
        'slug_en' => 'water-perf-en',
        'status' => BlogPostStatus::Published->value,
        'tags' => [$tag->id],
    ]);

    // Scheduled post: 404 before published_at, 200 automatically once time arrives
    $scheduledAt = now()->addHour();
    $scheduled = $savePost($editor, null, [
        'blog_category_id' => $cat->id,
        'author_id' => $editor->id,
        'title' => ['ar' => 'مقال مجدول', 'en' => 'Scheduled Post'],
        'excerpt' => ['ar' => 'مختصر', 'en' => 'Scheduled excerpt'],
        'body' => ['ar' => 'محتوى مجدول', 'en' => 'Scheduled body'],
        'slug_ar' => 'sched-post-ar',
        'slug_en' => 'sched-post-en',
        'status' => BlogPostStatus::Scheduled->value,
        'published_at' => $scheduledAt->toDateTimeString(),
        'tags' => [$tag->id],
    ]);

    $this->get('/en/blog/sched-post-en')->assertNotFound();
    $this->get('/en/blog')->assertOk()->assertSee('Watercolor Performance')->assertDontSee('Scheduled Post')->assertDontSee('Draft Guide');

    // Arabic normalized search + category + tag filters
    $this->get('/ar/blog?q='.urlencode('اداء').'&category=colors-ar&tag=kids-ar')
        ->assertOk()
        ->assertSee('أداء الرسم المائي');

    // Article detail renders Article JSON-LD & related posts
    $this->get('/en/blog/water-perf-en')
        ->assertOk()
        ->assertSee('Full brush guide')
        ->assertSee('application/ld+json', false);

    // Travel past scheduled time -> becomes visible automatically
    $this->travelTo($scheduledAt->addMinute());
    $this->get('/en/blog/sched-post-en')->assertOk()->assertSee('Scheduled body');
    $this->get('/en/blog')->assertOk()->assertSee('Scheduled Post');

    expect(Activity::query()->whereIn('event', ['blog.post_created', 'blog.category_saved', 'blog.tag_saved'])->count())
        ->toBeGreaterThanOrEqual(4);
})->group('T-P2-07', 'A1', 'YG-WEB-006');
