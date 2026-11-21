<?php

declare(strict_types=1);

use App\Domain\Seo\PublicUrl;
use App\Domain\Settings\GeneralSettings;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\HomepageBlock;
use App\Models\InstructorProfile;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;

uses(RefreshDatabase::class);

it('renders the branded public shell and only shows published featured content', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    CmsPage::query()->where('slug', 'about')->update(['status' => CmsPage::PUBLISHED]);

    foreach ([['Featured painting', 'published', true], ['Draft painting', 'draft', true], ['Ordinary painting', 'published', false]] as [$title, $status, $featured]) {
        Course::query()->create([
            'title' => ['en' => $title, 'ar' => $title],
            'slug_ar' => str($title)->slug()->toString(),
            'slug_en' => str($title)->slug()->toString(),
            'status' => $status,
            'access_mode' => $title === 'Featured painting' ? Course::ACCESS_SUBSCRIPTION : Course::ACCESS_FREE,
            'level' => 'beginner',
            'featured' => $featured,
        ]);
    }

    $page = $this->get('/en')->assertOk()
        ->assertSee('data-public-nav', false)
        ->assertSee(__('app.name'))
        ->assertSee('images/public-home-studio-640.webp', false)
        ->assertSee('/en/pages/about')
        ->assertSee('/contact')
        ->assertDontSee('/en/pages/privacy')
        ->assertSee('Featured painting')
        ->assertDontSee('Draft painting');
    $home = new Crawler($page->getContent());
    expect($home->filter('[data-public-brand] img[src$="/images/young-go-logo.png"]')->count())->toBe(2)
        ->and($home->filter('html.public-identity')->count())->toBe(1);
    expect($home->filter('[data-block-type="featured_courses"]')->text())->toContain(__('catalog.included_in_subscription'), __('courses.levels.beginner'))
        ->not->toContain('Ordinary painting')
        ->and($home->filter('[data-block-type="free_courses"]')->text())->toContain('Ordinary painting');
})->group('T-P2-03', 'A1', 'D-002', 'YG-WEB-001', 'YG-WEB-010');

it('links to the catalog filters from the homepage disciplines and uses saved footer links', function (string $locale): void {
    $this->withoutVite();
    $this->seed([CmsPageSeeder::class, CategorySeeder::class]);
    config(['public-site.social_links.facebook' => 'https://facebook.example/old']);
    $settings = app(GeneralSettings::class);
    $settings->social_facebook = 'https://facebook.example/current';
    $settings->social_linkedin = 'https://linkedin.example/current';
    $settings->save();

    $catalog = PublicUrl::catalog($locale);
    $response = $this->get('/'.$locale)->assertOk()
        ->assertSee('https://facebook.example/current', false)
        ->assertSee('https://linkedin.example/current', false)
        ->assertDontSee('https://facebook.example/old', false);
    $page = new Crawler($response->getContent());
    expect($page->filter('#home-disciplines a[href$="/'.$locale.'/courses#catalog-filters"]')->count())->toBe(1)
        ->and($page->filter('.site-footer-brand .site-footer-social a')->extract(['href']))->toBe([
            'https://facebook.example/current',
            'https://linkedin.example/current',
        ]);
    $this->get($catalog)->assertOk()->assertSee('id="catalog-filters"', false);
})->with(['ar', 'en'])->group('T-P2-03', 'A1', 'YG-WEB-001', 'YG-GAP-UX-001');

it('groups published help and policy pages in the public footer', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    CmsPage::query()->whereIn('seed_key', ['faq', 'privacy'])->update(['status' => CmsPage::PUBLISHED]);

    $page = $this->get('/en/courses')->assertOk();
    $footer = (new Crawler($page->getContent()))->filter('footer[data-public-footer]');

    expect($footer->filter('a[href$="/en/contact"]')->count())->toBe(1)
        ->and($footer->filter('.site-footer-grid > *')->count())->toBe(3)
        ->and($footer->filter('a[href$="/en/pages/faq"]')->count())->toBe(1)
        ->and($footer->filter('.site-footer-bottom a[href$="/en/pages/privacy"]')->count())->toBe(1)
        ->and($footer->filter('a[href$="/en/pages/terms"]')->count())->toBe(0)
        ->and($footer->filter('.site-footer-bottom a[href="'.route('consent.show').'"]')->count())->toBe(1)
        ->and($footer->filter('a[href$="/en/contact#contact-reasons-title"]')->count())->toBe(1)
        ->and($footer->filter('a[href$="/en/plans"]')->count())->toBe(0);

    CmsPage::query()->where('seed_key', 'terms')->update(['status' => CmsPage::PUBLISHED]);
    $terms = new Crawler($this->get('/en/pages/terms')->assertOk()->getContent());
    expect($terms->filter('.site-footer-bottom a[href$="/en/pages/terms"][aria-current="page"]')->count())->toBe(1);
})->group('T-P2-03', 'A1', 'YG-WEB-001');

it('uses one public shell with localized links across visitor pages', function (string $locale): void {
    $this->withoutVite();

    foreach (['courses', 'blog', 'contact'] as $path) {
        $response = $this->get('/'.$locale.'/'.$path)->assertOk()
            ->assertSee('data-public-shell', false)
            ->assertSee('site-footer-grid', false)
            ->assertSee('href="'.url('/'.$locale.'/contact').'"', false);
        $page = new Crawler($response->getContent());
        expect($page->filter('[data-public-footer] a[href$="/'.$locale.'/blog"]')->count())->toBe(1);
    }
})->with(['ar', 'en'])->group('T-P2-03', 'YG-WEB-001', 'YG-GAP-UX-001');

it('keeps disciplines in the footer and uses the requested three links in both public menus', function (string $locale): void {
    $this->withoutVite();
    $this->seed([CmsPageSeeder::class, CategorySeeder::class, RolePermissionSeeder::class]);
    CmsPage::query()->where('seed_key', 'about')->update(['status' => CmsPage::PUBLISHED]);
    $user = User::factory()->create()->assignRole('instructor_academy');
    InstructorProfile::query()->create([
        'user_id' => $user->id,
        'slug_ar' => 'instructor-ar',
        'slug_en' => 'instructor-en',
        'status' => InstructorProfile::APPROVED,
    ]);

    $anchor = 'a[href$="/'.$locale.'#home-disciplines"]';
    $page = new Crawler($this->get('/'.$locale.'/courses')->assertOk()->getContent());
    $header = $page->filter('.site-header-nav a')->extract(['href']);
    $menu = $page->filter('#public-menu [data-public-nav] a')->extract(['href']);
    $footer = $page->filter('footer nav a')->extract(['href']);
    expect($header)->toBe([
        url('/'.$locale.'/courses'),
        url('/'.$locale.'/pages/about'),
        url('/'.$locale.'/contact'),
    ])->and($menu)->toBe($header)
        ->and($footer)->toBe([
            url('/'.$locale.'/courses'),
            url('/'.$locale).'#home-disciplines',
            url('/'.$locale).'#home-instructors',
            url('/'.$locale.'/blog'),
            url('/'.$locale.'/pages/about'),
            url('/'.$locale.'/pages/about').'#about-features-title',
            url('/'.$locale.'/contact'),
            url('/'.$locale.'/contact').'#contact-reasons-title',
        ]);
    $this->get('/'.$locale)->assertOk()->assertSee('id="home-instructors"', false);
    $this->get('/'.$locale.'/pages/about')->assertOk()->assertSee('id="about-features-title"', false);
    $this->get('/'.$locale.'/contact')->assertOk()->assertSee('id="contact-reasons-title"', false);
    $labels = $locale === 'ar'
        ? ['الدورات', 'من نحن', 'تواصل معنا']
        : ['Courses', 'About us', 'Contact us'];
    expect($page->filter('.site-header-nav a')->each(fn (Crawler $link): string => $link->text()))->toBe($labels)
        ->and($page->filter('#public-menu [data-public-nav] a')->each(fn (Crawler $link): string => $link->text()))->toBe($labels);

    HomepageBlock::query()->whereIn('type', ['categories', 'instructors'])->update(['visible' => false]);
    CmsPage::query()->where('seed_key', 'about')->update(['status' => CmsPage::DRAFT]);
    $page = new Crawler($this->get('/'.$locale.'/courses')->assertOk()->getContent());
    expect($page->filter($anchor)->count())->toBe(0);
    expect($page->filter('.site-header-nav a')->extract(['href']))->toBe([url('/'.$locale.'/courses'), url('/'.$locale.'/contact')]);
    expect($page->filter('footer nav a')->extract(['href']))->toBe([
        url('/'.$locale.'/courses'),
        url('/'.$locale.'/blog'),
        url('/'.$locale.'/contact'),
        url('/'.$locale.'/contact').'#contact-reasons-title',
    ]);
})->with(['ar', 'en'])->group('T-P2-03', 'A1', 'YG-WEB-001', 'YG-GAP-UX-001');

it('returns visitors to the localized home and contact page after a missing public URL', function (): void {
    $this->withoutVite();
    config(['app.support_email' => null]);

    $this->get('/ar/courses/vacant-course')->assertNotFound()
        ->assertSee('href="'.url('/ar').'"', false)
        ->assertSee('href="'.url('/ar/contact').'"', false);
})->group('T-P1-10', 'YG-GAP-UX-001');

it('keeps the guest language when a localized public page links to an unprefixed form', function (): void {
    $this->withoutVite();
    $this->withHeader('Accept-Language', 'en')->get('/ar/courses')->assertOk()->assertSee('lang="ar"', false);
    $this->get('/login')->assertOk()->assertSee('lang="ar"', false);
    $this->get('/cookies')->assertOk()->assertSee('lang="ar"', false);
    $this->get('/en/courses')->assertOk()->assertSee('lang="en"', false);
    $this->get('/register')->assertOk()->assertSee('lang="en"', false);
})->group('T-P2-03', 'T-P1-10', 'YG-GAP-UX-001');

it('switches language to the translated slug of the same published course', function (): void {
    $this->withoutVite();
    $course = Course::query()->create([
        'title' => ['ar' => 'اختبار الرسم', 'en' => 'Drawing test'],
        'slug_ar' => 'اختبار-الرسم', 'slug_en' => 'drawing-test',
        'status' => 'published', 'access_mode' => Course::ACCESS_FREE,
    ]);
    $arabicPath = parse_url(PublicUrl::course($course, 'ar'), PHP_URL_PATH);
    $englishPath = '/en/courses/drawing-test';

    $this->get($englishPath)->assertOk()->assertSee('name="return_to" value="'.$arabicPath.'"', false);
    $this->post('/locale', ['locale' => 'ar', 'return_to' => $arabicPath])->assertRedirect($arabicPath);
    $this->get($arabicPath)->assertOk()->assertSee('name="return_to" value="'.$englishPath.'"', false);
    $this->postJson('/locale', ['locale' => 'ar', 'return_to' => '/%2Fexample.test'])->assertUnprocessable()->assertJsonValidationErrors('return_to');
})->group('T-P2-06', 'T-P2-05', 'YG-WEB-004', 'YG-GAP-UX-001');

it('counts only published courses by format and respects visibility for the free courses section', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    Course::query()->create([
        'title' => ['en' => 'Published free illustration'], 'slug_en' => 'free-illustration', 'slug_ar' => 'free-illustration-ar',
        'format' => 'recorded', 'access_mode' => Course::ACCESS_FREE, 'status' => 'published',
    ]);
    Course::query()->create([
        'title' => ['en' => 'Draft live workshop'], 'slug_en' => 'draft-workshop', 'slug_ar' => 'draft-workshop-ar',
        'format' => 'live', 'access_mode' => Course::ACCESS_FREE, 'status' => 'draft',
    ]);

    $page = $this->get('/en')->assertOk()->assertDontSee('Draft live workshop');
    $home = new Crawler($page->getContent());
    expect($home->filter('[data-home-format="recorded"]')->count())->toBe(1)
        ->and($home->filter('[data-home-format="live"]')->count())->toBe(0)
        ->and($home->filter('[data-home-stat="courses"]')->text())->toBe('1')
        ->and($home->filter('[data-block-type="free_courses"]')->text())->toContain('Published free illustration');

    HomepageBlock::query()->where('type', 'free_courses')->update(['visible' => false]);
    $this->get('/en')->assertDontSee('data-block-type="free_courses"', false);
})->group('T-P2-03', 'YG-WEB-002', 'YG-WEB-010');

it('renders a localized newsletter signup with explicit consent and a contact fallback', function (string $locale): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);

    $page = $this->get('/'.$locale)->assertOk()->assertSee(url('/'.$locale.'/contact').'#contact-newsletter', false);
    $html = new Crawler($page->getContent());
    $form = $html->filter('[data-block-type="newsletter"] form');
    expect($form->attr('action'))->toBe(url('/'.$locale.'/newsletter'))
        ->and($form->filter('input[name="source"]')->attr('value'))->toBe('home')
        ->and($form->filter('input[name="consent"][checked]')->count())->toBe(0);
    expect($html->filter('[data-public-footer] form')->count())->toBe(0);

    $this->postJson('/'.$locale.'/newsletter', ['email' => 'home-reader@example.com'])->assertUnprocessable();
    $this->postJson('/'.$locale.'/newsletter', ['email' => 'home-reader@example.com', 'consent' => '1', 'source' => 'home'])->assertNoContent();
    $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'home-reader@example.com', 'locale' => $locale, 'source' => 'home']);

    $contact = new Crawler($this->get('/'.$locale.'/contact')->assertOk()->getContent());
    expect($contact->filter('#contact-newsletter form')->attr('action'))->toBe(url('/'.$locale.'/newsletter'));
})->with(['ar', 'en'])->group('T-P2-03', 'YG-WEB-002', 'YG-WEB-008', 'YG-WEB-010');

it('gives each visible newsletter form its own label targets', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    HomepageBlock::query()->where('type', 'newsletter')->firstOrFail()->replicate()->save();

    $forms = (new Crawler($this->get('/en')->assertOk()->getContent()))->filter('.home-newsletter-form');
    expect($forms->count())->toBe(2);
    foreach (['email', 'consent'] as $field) {
        $ids = $forms->filter('input[name="'.$field.'"]')->extract(['id']);
        expect(array_unique($ids))->toHaveCount(2);
        foreach ($ids as $id) {
            expect($forms->filter('label[for="'.$id.'"]')->count())->toBe(1);
        }
    }
})->group('T-P2-03', 'YG-WEB-002', 'YG-WEB-008');

it('links visual disciplines and the introductory guide to real destinations', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    $category = Category::query()->create([
        'name' => ['ar' => 'الرسم', 'en' => 'Drawing'],
        'slug_ar' => 'الرسم', 'slug_en' => 'drawing', 'status' => 'active',
    ]);

    $home = new Crawler($this->get('/en')->assertOk()->getContent());
    $drawing = $home->filter('[data-block-type="categories"] a[href="'.PublicUrl::category($category, 'en').'"]');
    $steps = '#'.$home->filter('[data-block-type="how_it_works"]')->attr('id');
    expect($drawing->filter('img')->attr('src'))->toEndWith('/images/homepage/drawing.webp')
        ->and($home->filter('[data-block-type="hero"] a[href="'.$steps.'"]')->count())->toBe(1)
        ->and($home->filter('[data-home-stat="categories"]')->text())->toBe('1');
})->group('T-P2-03', 'A1', 'YG-WEB-002', 'YG-WEB-010');

it('prioritizes the three requested published courses when staff have featured them', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    foreach (['drawing-basics-for-beginners', 'animation-lab', 'voice-acting-and-dubbing', 'other-featured'] as $slug) {
        Course::query()->create([
            'title' => ['en' => $slug], 'slug_ar' => $slug.'-ar', 'slug_en' => $slug,
            'status' => 'published', 'access_mode' => Course::ACCESS_FREE, 'featured' => true,
        ]);
    }

    $home = new Crawler($this->get('/en')->assertOk()->getContent());
    expect($home->filter('[data-block-type="featured_courses"] article h3')->extract(['_text']))
        ->toBe(['animation-lab', 'voice-acting-and-dubbing', 'drawing-basics-for-beginners']);
    expect($home->filter('[data-home-hero-course]')->attr('href'))->toBe(url('/en/courses/animation-lab'));
})->group('T-P2-03', 'YG-WEB-002', 'YG-WEB-010');

it('uses a published free offer and shows only published curriculum in homepage summaries', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    foreach ([['kids-art-colour-and-fun', Course::ACCESS_PAID], ['free-painting', Course::ACCESS_FREE]] as [$slug, $access]) {
        $course = Course::query()->create([
            'title' => ['en' => $slug], 'slug_ar' => $slug.'-ar', 'slug_en' => $slug,
            'status' => 'published', 'access_mode' => $access, 'featured' => $access === Course::ACCESS_FREE,
        ]);
    }
    $section = CourseSection::query()->create(['course_id' => $course->id, 'title' => ['en' => 'Painting'], 'published' => true, 'sort' => 1]);
    foreach ([['published', 600], ['draft', 6600]] as $sort => [$status, $seconds]) {
        $lesson = Lesson::query()->create(['title' => ['en' => 'Lesson'], 'type' => 'text', 'duration_seconds' => $seconds]);
        $lesson->item()->create(['course_id' => $course->id, 'section_id' => $section->id, 'sort' => $sort + 1, 'status' => $status]);
    }

    $home = new Crawler($this->get('/en')->assertOk()->getContent());
    $free = $home->filter('[data-block-type="free_courses"]');
    expect($free->filter('h2')->text())->toBe('free-painting')
        ->and($free->filter('.home-free-meta')->text())->toContain('1 lesson', '10 mins')
        ->and($home->filter('.home-course-duration')->text())->toBe('10 mins')
        ->and($home->filter('[data-card-lessons]')->text())->toBe('1 lesson');
})->group('T-P2-03', 'YG-WEB-002', 'YG-WEB-010');

it('refreshes old homepage defaults while preserving editorial copy and block settings', function (): void {
    $hero = HomepageBlock::query()->create([
        'type' => 'hero', 'title' => ['ar' => 'مساحة تتعلّم فيها وتبدع', 'en' => 'Our original studio'],
        'body' => ['ar' => 'نص مخصص'], 'visible' => false, 'sort' => 50,
    ]);
    $migration = require database_path('migrations/2026_11_21_190000_refresh_public_home_default_copy.php');
    $migration->up();
    $hero->refresh();

    expect($hero->getTranslation('title', 'ar'))->toBe(__('public-site.hero', [], 'ar'))
        ->and($hero->getTranslation('title', 'en'))->toBe('Our original studio')
        ->and($hero->getTranslation('body', 'ar'))->toBe('نص مخصص')
        ->and($hero->visible)->toBeFalse()
        ->and($hero->sort)->toBe(50);
})->group('T-P2-03', 'YG-WEB-002');

it('adds the new sections to a legacy homepage without replacing staff settings or restoring a removed section', function (bool $hasInstructorBlock): void {
    foreach (['hero', 'categories', 'featured_courses', 'how_it_works'] as $i => $type) {
        HomepageBlock::query()->create([
            'type' => $type, 'title' => ['en' => 'Custom '.$type], 'sort' => 20 + $i, 'visible' => $i !== 0,
        ]);
    }
    if ($hasInstructorBlock) {
        HomepageBlock::query()->create(['type' => 'instructors', 'title' => ['en' => 'Our mentors'], 'sort' => 24, 'visible' => false]);
    }
    $migration = require database_path('migrations/2026_11_21_190000_refresh_public_home_default_copy.php');
    $migration->up();

    $newSections = $hasInstructorBlock ? ['instructors', 'free_courses', 'blog', 'newsletter'] : ['free_courses', 'instructors', 'blog', 'newsletter'];
    expect(HomepageBlock::query()->orderBy('sort')->pluck('type')->all())
        ->toBe(['hero', 'categories', 'featured_courses', 'how_it_works', ...$newSections]);
    $this->assertDatabaseHas('homepage_blocks', ['type' => 'hero', 'sort' => 20, 'visible' => false]);
    expect(HomepageBlock::query()->where('type', 'hero')->firstOrFail()->getTranslation('title', 'en'))->toBe('Custom hero');
    if ($hasInstructorBlock) {
        $instructors = HomepageBlock::query()->where('type', 'instructors')->firstOrFail();
        expect($instructors->getTranslation('title', 'en'))->toBe('Our mentors')
            ->and($instructors->sort)->toBe(24)
            ->and($instructors->visible)->toBeFalse();
    }

    HomepageBlock::query()->where('type', 'blog')->delete();
    $migration->up();
    $this->assertDatabaseMissing('homepage_blocks', ['type' => 'blog']);
})->with([false, true])->group('T-P2-03', 'YG-WEB-002', 'YG-WEB-008');

it('leaves unfeatured courses in the catalog when staff have not featured any', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    Course::query()->create([
        'title' => ['en' => 'Unfeatured painting'], 'slug_en' => 'unfeatured-painting', 'slug_ar' => 'unfeatured-painting-ar',
        'access_mode' => Course::ACCESS_FREE, 'status' => 'published', 'featured' => false,
    ]);

    $home = new Crawler($this->get('/en')->assertOk()->getContent());
    $section = $home->filter('[data-block-type="featured_courses"]');
    expect($section->filter('article')->count())->toBe(0)
        ->and($section->filter('a')->attr('href'))->toBe(PublicUrl::catalog('en'));
})->group('T-P2-03', 'YG-WEB-002', 'YG-WEB-010');

it('shows only live articles in the homepage blog section', function (): void {
    $this->withoutVite();
    $this->seed(CmsPageSeeder::class);
    BlogPost::query()->create([
        'title' => ['en' => 'A guide to ink'], 'body' => ['en' => 'The basics of drawing with ink.'],
        'slug_en' => 'guide-to-ink', 'slug_ar' => 'guide-to-ink-ar', 'status' => 'published', 'published_at' => now()->subHour(),
    ]);
    BlogPost::query()->create([
        'title' => ['en' => 'Upcoming sketch guide'], 'body' => ['en' => 'Learning to sketch.'],
        'slug_en' => 'sketch-guide', 'slug_ar' => 'sketch-guide-ar', 'status' => 'scheduled', 'published_at' => now()->addHour(),
    ]);

    $this->get('/en')->assertOk()
        ->assertSee('data-block-type="blog"', false)
        ->assertSee('href="'.url('/en/blog/guide-to-ink').'"', false)
        ->assertSee('A guide to ink')
        ->assertDontSee('Upcoming sketch guide');
    $page = new Crawler($this->get('/en/courses')->assertOk()->getContent());
    expect($page->filter('[data-public-footer] a[href$="/en/blog"]')->count())->toBe(1)
        ->and($page->filter('[data-public-header] a[href$="/en/blog"]')->count())->toBe(0);
})->group('T-P2-03', 'YG-WEB-002');
