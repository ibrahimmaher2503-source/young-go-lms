<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Content\BlogPostStatus;
use App\Domain\Seo\Actions\BuildSitemap;
use App\Domain\Seo\PublicUrl;
use App\Domain\Seo\Queries\ResolveRedirect;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\SeoRedirect;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('generates sitemap.xml with live public entries only and omits drafts, private, scheduled future, and learner areas', function (): void {
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $cat = Category::query()->create(['name' => ['ar' => 'فنون', 'en' => 'Arts'], 'slug_ar' => 'arts-ar', 'slug_en' => 'arts-en', 'status' => 'active', 'sort' => 1]);
    $hiddenCat = Category::query()->create(['name' => ['ar' => 'مخفي', 'en' => 'Hidden'], 'slug_ar' => 'hid-ar', 'slug_en' => 'hid-en', 'status' => 'inactive', 'sort' => 2]);

    // Published course with both locales vs AR-only published course vs draft course
    $pubBoth = Course::query()->create([
        'title' => ['ar' => 'رسم عام', 'en' => 'Public Drawing'],
        'slug_ar' => 'pub-draw-ar',
        'slug_en' => 'pub-draw-en',
        'status' => CourseStatus::Published->value,
        'category_id' => $cat->id,
    ]);
    $pubArOnly = Course::query()->create([
        'title' => ['ar' => 'دورة عربية فقط'],
        'slug_ar' => 'ar-only-course',
        'slug_en' => 'ar-only-course-en',
        'status' => CourseStatus::Published->value,
        'category_id' => Category::query()->create(['name' => ['ar' => 'تصنيف عربي'], 'slug_ar' => 'cat-ar-only', 'slug_en' => 'cat-ar-only-en', 'status' => 'active', 'sort' => 3])->id,
    ]);
    $draftCourse = Course::query()->create([
        'title' => ['ar' => 'مسودة', 'en' => 'Draft Course'],
        'slug_ar' => 'draft-course-ar',
        'slug_en' => 'draft-course-en',
        'status' => CourseStatus::Draft->value,
    ]);

    // CMS published vs draft
    CmsPage::query()->create(['title' => ['ar' => 'عن المنصة', 'en' => 'About Us'], 'slug' => 'about-seo', 'body' => ['ar' => 'نص', 'en' => 'Body'], 'status' => CmsPage::PUBLISHED]);
    CmsPage::query()->create(['title' => ['ar' => 'مسودة صفحة', 'en' => 'Draft Page'], 'slug' => 'draft-seo', 'body' => ['ar' => 'نص', 'en' => 'Body'], 'status' => 'draft']);

    // Blog live vs future scheduled
    $bCat = BlogCategory::query()->create(['name' => ['ar' => 'مقالات', 'en' => 'Articles'], 'slug_ar' => 'bcat-ar', 'slug_en' => 'bcat-en', 'status' => BlogCategory::ACTIVE, 'sort' => 1]);
    BlogPost::query()->create([
        'blog_category_id' => $bCat->id,
        'author_id' => $instructor->id,
        'title' => ['ar' => 'مقال منشور', 'en' => 'Live Post'],
        'excerpt' => ['ar' => 'مختصر', 'en' => 'Excerpt'],
        'body' => ['ar' => 'محتوى', 'en' => 'Content'],
        'slug_ar' => 'live-post-ar',
        'slug_en' => 'live-post-en',
        'status' => BlogPostStatus::Published,
        'published_at' => now()->subDay(),
    ]);
    BlogPost::query()->create([
        'blog_category_id' => $bCat->id,
        'author_id' => $instructor->id,
        'title' => ['ar' => 'مقال قادم', 'en' => 'Future Post'],
        'excerpt' => ['ar' => 'م', 'en' => 'E'],
        'body' => ['ar' => 'م', 'en' => 'C'],
        'slug_ar' => 'future-post-ar',
        'slug_en' => 'future-post-en',
        'status' => BlogPostStatus::Scheduled,
        'published_at' => now()->addDays(2),
    ]);

    // Approved active instructor vs draft instructor
    InstructorProfile::query()->create([
        'user_id' => $instructor->id,
        'slug_ar' => 'pub-inst-ar',
        'slug_en' => 'pub-inst-en',
        'display_name' => ['ar' => 'أ. نور', 'en' => 'Nour'],
        'status' => InstructorProfile::APPROVED,
    ]);
    $draftInstUser = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    InstructorProfile::query()->create([
        'user_id' => $draftInstUser->id,
        'slug_ar' => 'draft-inst-ar',
        'slug_en' => 'draft-inst-en',
        'display_name' => ['ar' => 'مسودة', 'en' => 'Draft'],
        'status' => InstructorProfile::DRAFT,
    ]);

    app(BuildSitemap::class)->refresh();
    $res = $this->get(route('seo.sitemap'))->assertOk();
    $xml = $res->getContent();
    expect($res->headers->get('Content-Type'))->toContain('application/xml')
        ->and($xml)->toContain(PublicUrl::course($pubBoth, 'ar'))
        ->toContain(PublicUrl::course($pubBoth, 'en'))
        ->toContain(PublicUrl::course($pubArOnly, 'ar'))
        ->toContain('/ar/categories/cat-ar-only')
        ->not->toContain('/en/categories/cat-ar-only')
        ->not->toContain('/en/courses/ar-only-course')
        ->not->toContain('draft-course-ar')
        ->not->toContain('draft-seo')
        ->not->toContain('future-post-ar')
        ->not->toContain('hid-en')
        ->not->toContain('draft-inst-en')
        ->toContain('pub-inst-ar')
        ->toContain('about-seo')
        ->toContain('live-post-en')
        ->not->toContain('/learn')
        ->not->toContain('/admin');
})->group('T-P2-06', 'A1', 'YG-WEB-009');

it('serves robots.txt by environment indexing flag, renders course canonical/hreflang tags, and resolves safe legacy redirects', function (): void {
    // Indexing off (default test env) -> Disallow: /
    Config::set('seo.indexing', false);
    $off = $this->get(route('seo.robots'))->assertOk()->getContent();
    expect($off)->toContain("User-agent: *\nDisallow: /");

    // Indexing on -> private areas disallowed + Sitemap present
    Config::set('seo.indexing', true);
    $on = $this->get(route('seo.robots'))->assertOk()->getContent();
    expect($on)->toContain('Disallow: /admin')
        ->toContain('Disallow: /learn')
        ->toContain('Allow: /')
        ->toContain('Sitemap: '.url('/sitemap.xml'));

    // Course detail canonical + hreflang + x-default + instructor JSON-LD profile URL (excluding missing locale when AR-only)
    $courseInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    InstructorProfile::query()->create([
        'user_id' => $courseInstructor->id,
        'slug_ar' => 'ceramics-inst-ar',
        'slug_en' => 'ceramics-inst-en',
        'display_name' => ['ar' => 'أ. ليلى', 'en' => 'Layla'],
        'status' => InstructorProfile::APPROVED,
    ]);
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة خزف', 'en' => 'Ceramics Course'],
        'slug_ar' => 'ceramics-ar',
        'slug_en' => 'ceramics-en',
        'status' => CourseStatus::Published->value,
    ]);
    $course->instructors()->attach($courseInstructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $this->get('/en/courses/ceramics-en')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.PublicUrl::course($course, 'en').'"', false)
        ->assertSee('hreflang="ar"', false)
        ->assertSee('hreflang="en"', false)
        ->assertSee('hreflang="x-default"', false)
        ->assertSee('/en/instructors/ceramics-inst-en', false);

    $arOnlyDetail = Course::query()->create([
        'title' => ['ar' => 'دورة عربية حصريا'],
        'slug_ar' => 'ar-only-hreflang',
        'slug_en' => 'ar-only-hreflang-en',
        'status' => CourseStatus::Published->value,
    ]);
    $this->get('/en/courses/ar-only-hreflang-en')
        ->assertOk()
        ->assertSee('hreflang="ar"', false)
        ->assertDontSee('hreflang="en"', false);

    // Safe redirects (preserve query string; block open external redirects & self-loops)
    SeoRedirect::query()->create(['from_path' => '/old-course', 'to_path' => '/ar/courses/ceramics-ar', 'status_code' => 301]);
    SeoRedirect::query()->create(['from_path' => '/evil-open', 'to_path' => 'https://evil.example.com', 'status_code' => 302]);
    SeoRedirect::query()->create(['from_path' => '/proto-rel', 'to_path' => '//evil.example.com/x', 'status_code' => 301]);
    SeoRedirect::query()->create(['from_path' => '/self-loop', 'to_path' => '/self-loop', 'status_code' => 301]);

    $this->get('/old-course?utm_source=legacy')
        ->assertStatus(301)
        ->assertRedirect('/ar/courses/ceramics-ar?utm_source=legacy');

    $resolver = app(ResolveRedirect::class);
    expect($resolver->for('/evil-open'))->toBeNull()
        ->and($resolver->for('/proto-rel'))->toBeNull()
        ->and($resolver->for('/self-loop'))->toBeNull();
    $this->get('/evil-open')->assertNotFound();
    $this->get('/nonexistent-legacy-url')->assertNotFound();
})->group('T-P2-06', 'A1', 'A2', 'YG-WEB-009', 'ED-018');
