<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Content\BlogPostStatus;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\CmsPages\Pages\ListCmsPages;
use App\Filament\Resources\HomepageBlocks\Pages\ListHomepageBlocks;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\ContactEnquiry;
use App\Models\Course;
use App\Models\HomepageBlock;
use App\Models\InstructorProfile;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/** Acts as one user: a fresh session per actor, the way a person switching accounts gets one. */
function p2As($test, ?User $user = null)
{
    $test->flushSession();
    app('auth')->forgetGuards();

    return $user === null ? $test : $test->actingAs($user);
}

/** The JSON-LD blocks of a rendered page, decoded. */
function p2JsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(static fn (string $json): array => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $matches[1]);
}

/**
 * T-P2-MG demo script, executed over HTTP against the seeded roles:
 * homepage blocks in AR and EN (edited and reordered) → a CMS page drafted, previewed and published → Arabic search
 * variants and filters in the catalog → course page with price, canonical, hreflang and Course JSON-LD → blog with a
 * scheduled post hidden → approved instructor profile and category landing page → robots.txt and sitemap.xml → contact
 * enquiry and newsletter subscribe with a login-free signed unsubscribe.
 */
it('runs the P2 demo script end to end', function (): void {
    // Accounts: a content manager edits the public site, an instructor teaches, and visitors are anonymous.
    $editor = User::factory()->create(['locale' => 'en'])->assignRole('content_manager');
    $instructor = User::factory()->create(['status' => 'active', 'name' => 'Nour Instructor'])->assignRole('instructor_academy');
    $pendingInstructor = User::factory()->create(['status' => 'active', 'name' => 'Pending Applicant'])->assignRole('instructor_academy');

    // Categories and courses: a published paid live theatre course (featured, priced in EGP), a free recorded drawing
    // course, and a draft course that must never appear publicly.
    $theatre = Category::query()->create([
        'name' => ['en' => 'Theatre', 'ar' => 'المسرح'], 'slug_en' => 'theatre', 'slug_ar' => 'masrah', 'status' => 'active', 'sort' => 1,
    ]);
    $drawingCategory = Category::query()->create([
        'name' => ['en' => 'Drawing', 'ar' => 'الرسم'], 'slug_en' => 'drawing', 'slug_ar' => 'rasm', 'status' => 'active', 'sort' => 2,
    ]);
    $performance = Course::query()->create([
        'title' => ['ar' => 'أداء مسرحية', 'en' => 'Theatre Performance Studio'], 'slug_ar' => 'ar-theatre', 'slug_en' => 'theatre-performance',
        'status' => CourseStatus::Published, 'access_mode' => 'paid', 'format' => 'live', 'level' => 'beginner', 'content_language' => 'ar',
        'category_id' => $theatre->id, 'featured' => true, 'min_age' => 8, 'max_age' => 12,
    ]);
    CoursePrice::query()->create(['course_id' => $performance->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => 25000]);
    $performance->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $charcoal = Course::query()->create([
        'title' => ['ar' => 'رسم بالفحم', 'en' => 'Charcoal Drawing'], 'slug_ar' => 'ar-charcoal', 'slug_en' => 'charcoal-drawing',
        'status' => CourseStatus::Published, 'access_mode' => 'free', 'format' => 'recorded', 'level' => 'beginner', 'content_language' => 'ar',
        'category_id' => $drawingCategory->id, 'featured' => false,
    ]);
    Course::query()->create([
        'title' => ['en' => 'Secret Draft Course', 'ar' => 'مسودة سرية'], 'slug_en' => 'secret-draft', 'slug_ar' => 'ar-secret-draft',
        'status' => CourseStatus::Draft, 'access_mode' => 'free', 'category_id' => $theatre->id,
    ]);

    // 1. Homepage blocks: the editor's blocks render in both languages, and editing order and visibility shows on the homepage.
    $banner = HomepageBlock::query()->create(['type' => 'cta', 'title' => ['en' => 'Demo banner', 'ar' => 'لافتة تجريبية'], 'sort' => 1, 'visible' => true]);
    $featuredBlock = HomepageBlock::query()->create(['type' => 'featured_courses', 'title' => ['en' => 'Featured this term', 'ar' => 'المميزة هذا الفصل'], 'sort' => 2, 'visible' => true]);
    $this->withSession(['locale' => 'en'])->get('/en')->assertOk()
        ->assertSee('lang="en" dir="ltr"', false)
        ->assertSeeInOrder(['Demo banner', 'Featured this term'])
        ->assertSee('Theatre Performance Studio');
    $this->withSession(['locale' => 'ar'])->get('/ar')->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false)->assertSee('لافتة تجريبية')->assertSee('المميزة هذا الفصل');

    p2As($this, $editor);
    $this->get('/admin/homepage-blocks')->assertOk();
    Livewire::test(ListHomepageBlocks::class)->callAction(TestAction::make('edit')->table($banner), ['sort' => 3])
        ->assertHasNoActionErrors();
    Livewire::test(ListHomepageBlocks::class)->callAction(TestAction::make('edit')->table($featuredBlock), ['visible' => false])
        ->assertHasNoActionErrors();
    p2As($this)->withSession(['locale' => 'en'])->get('/en')->assertOk()
        ->assertSee('Demo banner')->assertDontSee('Featured this term');

    // 2. CMS page: a draft is 404 publicly and previewable only by editors; publishing makes it live in both languages.
    p2As($this, $editor);
    $this->get('/admin/cms-pages')->assertOk();
    Livewire::test(ListCmsPages::class)->callAction('create', [
        'slug' => 'our-workshops',
        'title' => ['ar' => 'ورش عملنا', 'en' => 'Our Workshops'],
        'body' => ['ar' => 'محتوى الورش', 'en' => 'Workshop programme details'],
        'status' => 'draft',
        'template' => 'standard',
    ])->assertHasNoActionErrors();
    $page = CmsPage::query()->where('slug', 'our-workshops')->sole();
    p2As($this)->get('/pages/our-workshops')->assertNotFound();
    p2As($this, $editor)->get(URL::temporarySignedRoute('cms.preview', now()->addMinutes(5), ['page' => $page]))
        ->assertOk()->assertSee('Workshop programme details');
    p2As($this, $editor);
    Livewire::test(ListCmsPages::class)->callAction(TestAction::make('edit')->table($page), ['status' => 'published'])
        ->assertHasNoActionErrors();
    p2As($this)->withSession(['locale' => 'en'])->get('/pages/our-workshops')->assertOk()->assertSee('Workshop programme details');

    // 3. Catalog: an Arabic spelling variant finds the theatre course, combined filters narrow the list, and the draft never appears.
    p2As($this)->get('/ar/courses?'.http_build_query(['q' => 'اداء']))->assertOk()
        ->assertSee('أداء مسرحية')->assertDontSee('رسم بالفحم');
    p2As($this)->get('/en/courses?'.http_build_query(['format' => 'live', 'access' => 'paid', 'category' => $theatre->id]))->assertOk()
        ->assertSee('Theatre Performance Studio')->assertDontSee('Charcoal Drawing');
    p2As($this)->get('/en/courses?'.http_build_query(['access' => 'free']))->assertOk()
        ->assertSee('Charcoal Drawing')->assertDontSee('Theatre Performance Studio')->assertDontSee('Secret Draft Course');
    p2As($this)->get('/en/courses')->assertOk()->assertDontSee('Secret Draft Course');

    // 4. Course page: price, access model, Course JSON-LD, canonical and hreflang for both languages; a draft answers 404.
    $detail = p2As($this)->withSession(['locale' => 'en'])->get('/en/courses/theatre-performance')->assertOk()
        ->assertSee('Theatre Performance Studio')->assertSee('250')
        ->assertSee('hreflang="ar"', false)->assertSee('hreflang="en"', false)->assertSee('rel="canonical"', false);
    $course = collect(p2JsonLd($detail->getContent()))->firstWhere('@type', 'Course');
    expect($course)->not->toBeNull()
        ->and($course['name'])->toBe('Theatre Performance Studio')
        ->and($course['inLanguage'])->toBe('ar');
    p2As($this)->get('/en/courses/secret-draft')->assertNotFound();

    // 5. Blog: the live post is listed and readable, a scheduled post stays hidden until its time.
    $writer = $instructor;
    $news = BlogCategory::query()->create([
        'name' => ['en' => 'News', 'ar' => 'أخبار'], 'slug_en' => 'news', 'slug_ar' => 'akhbar', 'status' => BlogCategory::ACTIVE, 'sort' => 1,
    ]);
    BlogPost::query()->create([
        'blog_category_id' => $news->id, 'author_id' => $writer->id, 'title' => ['en' => 'Stage Presence Tips', 'ar' => 'نصائح الحضور'],
        'excerpt' => ['en' => 'Short tips', 'ar' => 'نصائح'], 'body' => ['en' => 'Breathing and posture for young actors.', 'ar' => 'محتوى'],
        'slug_en' => 'stage-presence-tips', 'slug_ar' => 'nasaih-hudur', 'status' => BlogPostStatus::Published, 'published_at' => now()->subDay(),
    ]);
    BlogPost::query()->create([
        'blog_category_id' => $news->id, 'author_id' => $writer->id, 'title' => ['en' => 'Upcoming Showcase', 'ar' => 'عرض قادم'],
        'excerpt' => ['en' => 'Soon', 'ar' => 'قريبا'], 'body' => ['en' => 'Showcase details.', 'ar' => 'تفاصيل'],
        'slug_en' => 'upcoming-showcase', 'slug_ar' => 'ard-qadim', 'status' => BlogPostStatus::Scheduled, 'published_at' => now()->addDays(3),
    ]);
    p2As($this)->get('/en/blog')->assertOk()->assertSee('Stage Presence Tips')->assertDontSee('Upcoming Showcase');
    p2As($this)->get('/en/blog/stage-presence-tips')->assertOk()->assertSee('Breathing and posture')
        ->assertSee('application/ld+json', false);
    p2As($this)->get('/en/blog/upcoming-showcase')->assertNotFound();

    // 6. Instructor profile (approved only) with the published course; category landing page with its course.
    InstructorProfile::query()->create([
        'user_id' => $instructor->id, 'slug_ar' => 'nour-ar', 'slug_en' => 'nour-art',
        'display_name' => ['ar' => 'نور', 'en' => 'Nour Coach'], 'headline' => ['en' => 'Stage coach'], 'bio' => ['en' => 'Teaches theatre.'],
        'status' => InstructorProfile::APPROVED,
    ]);
    InstructorProfile::query()->create([
        'user_id' => $pendingInstructor->id, 'slug_ar' => 'pending-ar', 'slug_en' => 'pending-applicant',
        'display_name' => ['ar' => 'قيد المراجعة', 'en' => 'Pending Applicant'], 'status' => InstructorProfile::DRAFT,
    ]);
    $profile = p2As($this)->get('/en/instructors/nour-art')->assertOk()->assertSee('Nour Coach')
        ->assertSee('Theatre Performance Studio')->assertDontSee($instructor->email);
    expect(collect(p2JsonLd($profile->getContent()))->pluck('@type')->all())->toContain('Person');
    p2As($this)->get('/en/instructors/pending-applicant')->assertNotFound();
    expect(app(PublicInstructors::class)->find('pending-applicant'))->toBeNull();

    p2As($this)->get('/en/categories/theatre')->assertOk()->assertSee('Theatre Performance Studio')
        ->assertDontSee('Charcoal Drawing')->assertDontSee('Secret Draft Course');

    // 7. Crawler files: robots blocks everything outside production, and when indexing is on it disallows private areas
    // and points at the sitemap. The sitemap lists only live public content, with hreflang alternates.
    p2As($this)->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false);
    Config::set('seo.indexing', true);
    p2As($this)->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /admin', false)->assertSee('Disallow: /learn', false)->assertSee('Sitemap:', false);
    $sitemap = p2As($this)->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = $sitemap->getContent();
    expect($xml)->toContain('/courses/theatre-performance')
        ->toContain('/pages/our-workshops')
        ->toContain('/blog/stage-presence-tips')
        ->toContain('/instructors/nour-art')
        ->toContain('/categories/theatre')
        ->toContain('hreflang=')
        ->not->toContain('charcoal-drawing-draft')
        ->not->toContain('secret-draft')
        ->not->toContain('upcoming-showcase')
        ->not->toContain('pending-applicant')
        ->not->toContain('/learn')
        ->not->toContain('/admin');

    // 8. Contact enquiry: a visitor without an account submits; a honeypot submission is dropped silently.
    p2As($this)->get('/contact')->assertOk();
    p2As($this)->post(route('contact.store'), [
        'name' => 'Mariam', 'email' => 'mariam@example.test', 'message' => 'Please send details about the theatre course.',
        Config::string('contact.honeypot_field') => 'http://spam.example.test',
    ])->assertNoContent();
    expect(ContactEnquiry::query()->count())->toBe(0);
    p2As($this)->post(route('contact.store'), [
        'name' => 'Mariam', 'email' => 'mariam@example.test', 'message' => 'Please send details about the theatre course.',
    ])->assertNoContent();
    expect(ContactEnquiry::query()->where('email', 'mariam@example.test')->count())->toBe(1);

    // 9. Newsletter: subscribe with consent, then unsubscribe through the signed link without logging in; a forged link is refused.
    p2As($this)->get('/newsletter')->assertOk();
    p2As($this)->post(route('newsletter.store'), ['email' => 'Reader@Example.test', 'consent' => '1', 'source' => 'footer'])->assertNoContent();
    $subscriber = NewsletterSubscriber::query()->where('email', 'reader@example.test')->sole();
    expect($subscriber->isSubscribed())->toBeTrue();
    p2As($this)->get($subscriber->unsubscribeUrl().'&forged=1')->assertForbidden();
    p2As($this)->get($subscriber->unsubscribeUrl())->assertOk();
    expect($subscriber->fresh()->isSubscribed())->toBeFalse();
})->group('T-P2-MG', 'A1');
