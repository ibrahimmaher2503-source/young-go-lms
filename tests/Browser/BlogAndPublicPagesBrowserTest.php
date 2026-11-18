<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Content\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders public blog index and post accessibly in AR and EN across mobile and desktop', function (string $locale, int $width, int $height): void {
    $this->seed(RolePermissionSeeder::class);
    $editor = User::factory()->create(['status' => 'active'])->assignRole('content_manager');
    $cat = BlogCategory::query()->create([
        'name' => ['ar' => 'تلوين', 'en' => 'Coloring'],
        'slug_ar' => 'col-ar',
        'slug_en' => 'col-en',
        'status' => BlogCategory::ACTIVE,
        'sort' => 1,
    ]);
    BlogPost::query()->create([
        'blog_category_id' => $cat->id,
        'author_id' => $editor->id,
        'title' => ['ar' => 'أساسيات التظليل', 'en' => 'Shading Basics'],
        'excerpt' => ['ar' => 'مقدمة سريعة في التظليل.', 'en' => 'A quick introduction to shading.'],
        'body' => ['ar' => 'نبدأ بالقلم الرصاص الناعم ثم نتدرج.', 'en' => 'Start with a soft pencil and layer smoothly.'],
        'slug_ar' => 'shading-ar',
        'slug_en' => 'shading-en',
        'status' => BlogPostStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    foreach (['index' => "/{$locale}/blog", 'post' => "/{$locale}/blog/".($locale === 'ar' ? 'shading-ar' : 'shading-en')] as $surface => $url) {
        $page = $this->visit($url)->resize($width, $height);
        $page->script('async () => { await document.fonts.ready; }');
        expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
            ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

        $dir = base_path('tests/Browser/screenshots/T-P2-07');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $page->screenshot(fullPage: false, filename: "../screenshots/T-P2-07/blog-{$surface}-{$locale}-{$width}");
    }
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])
    ->group('T-P2-07', 'A2', 'YG-WEB-006')
    ->group('browser');

it('renders public instructor profile and category landing page accessibly in AR and EN', function (string $locale, int $width, int $height): void {
    $this->seed(RolePermissionSeeder::class);
    $instUser = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $cat = Category::query()->create([
        'name' => ['ar' => 'فنون الأطفال', 'en' => 'Kids Art'],
        'description' => ['ar' => 'برامج إبداعية ممتعة', 'en' => 'Fun creative studios'],
        'slug_ar' => 'kids-art-ar',
        'slug_en' => 'kids-art-en',
        'status' => 'active',
        'sort' => 1,
    ]);
    $profile = InstructorProfile::query()->create([
        'user_id' => $instUser->id,
        'slug_ar' => 'omar-art-ar',
        'slug_en' => 'omar-art-en',
        'display_name' => ['ar' => 'أ. عمر الخطاط', 'en' => 'Omar Artist'],
        'headline' => ['ar' => 'مدرب خط ورسم', 'en' => 'Calligraphy & Art Coach'],
        'bio' => ['ar' => 'خبرة عشر سنوات مع الأطفال.', 'en' => 'Ten years coaching young creators.'],
        'status' => InstructorProfile::APPROVED,
    ]);
    $profile->disciplines()->sync([$cat->id]);

    $course = Course::query()->create([
        'title' => ['ar' => 'استوديو الخط', 'en' => 'Studio Calligraphy'],
        'slug_ar' => 'callig-ar',
        'slug_en' => 'callig-en',
        'status' => CourseStatus::Published->value,
        'category_id' => $cat->id,
    ]);
    $course->instructors()->attach($instUser->id, ['role' => 'instructor', 'scope' => 'manage']);

    $urls = [
        'instructor' => "/{$locale}/instructors/".($locale === 'ar' ? 'omar-art-ar' : 'omar-art-en'),
        'category' => "/{$locale}/categories/".($locale === 'ar' ? 'kids-art-ar' : 'kids-art-en'),
    ];
    foreach ($urls as $surface => $url) {
        $page = $this->visit($url)->resize($width, $height);
        $page->script('async () => { await document.fonts.ready; }');
        expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
            ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

        $dir = base_path('tests/Browser/screenshots/T-P2-09');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $page->screenshot(fullPage: false, filename: "../screenshots/T-P2-09/{$surface}-{$locale}-{$width}");
    }
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])
    ->group('T-P2-09', 'A3', 'YG-GAP-CMS-001', 'YG-GAP-CMS-002')
    ->group('browser');
